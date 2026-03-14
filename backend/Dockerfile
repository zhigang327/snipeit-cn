FROM php:8.2-fpm

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 系统依赖
RUN apt-get update && apt-get install -y \
    curl wget git \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    zip unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP 扩展
RUN docker-php-ext-install pdo pdo_mysql mbstring bcmath zip opcache

# Composer
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=600

WORKDIR /var/www/html

# 先只复制依赖描述文件，利用 Docker 层缓存
COPY composer.json composer.lock ./

# 配置国内镜像并安装依赖（不跑 scripts，避免依赖 .env）
# 优先腾讯云镜像；lock 文件无效时先 update 生成再 install
RUN composer config -g repo.packagist composer https://mirrors.cloud.tencent.com/composer/ 2>/dev/null \
    || composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    && LOCK_SIZE=$(wc -c < composer.lock | tr -d ' ') \
    && if [ "$LOCK_SIZE" -lt 100 ]; then \
         echo "composer.lock 无效，先生成..."; \
         composer update --no-install --no-scripts --no-interaction --ignore-platform-reqs --prefer-dist; \
       fi \
    && composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs \
        --optimize-autoloader

# 复制全部源码
COPY . .

# 目录权限
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x artisan

# 复制启动脚本
COPY startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

EXPOSE 9000

CMD ["bash", "/usr/local/bin/startup.sh"]
