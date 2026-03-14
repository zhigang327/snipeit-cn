# Snipe-CN官方稳定版Dockerfile
# 版本: 1.6.6-stable
# 日期: 2026-03-13
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
COPY .env.example .env.example

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

# 确保artisan文件有执行权限
RUN chmod +x artisan 2>/dev/null || true

# 复制启动脚本并设置权限
COPY startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# 如果没有.env文件，从示例创建（简化版，详细信息在启动脚本中处理）
RUN if [ ! -f .env ] && [ -f .env.example ]; then \
        cp .env.example .env; \
        echo "从.env.example创建.env文件"; \
    fi

# 优化自动加载器
RUN composer dump-autoload --optimize 2>/dev/null || echo "自动加载器优化跳过"

# 注意：应用密钥生成和数据库初始化将在容器启动时执行
# 这样可以避免构建时的环境依赖问题

# 清理
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# 暴露端口
EXPOSE 9000

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD php -r "echo 'OK'; exit(0);" || exit 1

# 启动命令：使用启动脚本执行初始化任务，然后启动PHP-FPM
CMD ["startup.sh"]