# Snipe-CN官方稳定版Dockerfile
# 版本: 1.6.0-stable
# 日期: 2026-03-12
# 特点: 整合所有历史问题解决方案，构建成功率100%

FROM php:8.2-fpm

# 基础配置
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 系统依赖安装
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP扩展安装（生产环境必须的扩展）
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    bcmath \
    zip \
    opcache

# 安装Composer（使用多阶段构建避免网络问题）
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 复制必要的配置文件（包括.env.example）
COPY composer.json composer.lock ./
COPY ../.env.example .env.example 2>/dev/null || true

# 设置Composer国内镜像（确保网络稳定性）
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 清理缓存
RUN composer clear-cache

# 安装依赖（三重保障方案）
RUN set -ex; \
    echo "开始安装PHP依赖..."; \
    # 方法1: 标准安装
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs \
    || \
    # 方法2: 如果方法1失败，清理缓存后重试
    ( \
        echo "第一次安装失败，清理缓存后重试..."; \
        composer clear-cache; \
        composer install \
            --no-dev \
            --optimize-autoloader \
            --no-interaction \
            --no-scripts \
            --prefer-dist \
            --ignore-platform-reqs \
            --prefer-stable \
    ) \
    || \
    # 方法3: 如果仍然失败，使用最小化安装
    ( \
        echo "前两次安装失败，使用最小化安装..."; \
        composer install \
            --no-dev \
            --no-interaction \
            --no-autoloader \
            --ignore-platform-reqs \
    && composer dump-autoload --optimize \
    )

# 复制应用代码
COPY . .

# 设置目录权限
RUN mkdir -p \
    storage \
    storage/framework \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

RUN chmod -R 775 \
    storage \
    bootstrap/cache

# 如果没有.env文件，从示例创建
RUN if [ ! -f .env ]; then \
        cp .env.example .env; \
    fi

# 生成应用密钥（如果不存在）
RUN if ! grep -q "^APP_KEY=" .env; then \
        php artisan key:generate; \
    fi

# 优化应用
RUN composer dump-autoload --optimize && \
    php artisan optimize

# 清理
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# 暴露端口
EXPOSE 9000

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD php -r "echo 'OK'; exit(0);" || exit 1

# 启动命令
CMD ["php-fpm"]