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

WORKDIR /var/www/html

# 先只复制依赖描述文件，利用 Docker 层缓存
COPY composer.json composer.lock ./

# 配置阿里云镜像，安装依赖（不跑 scripts，避免依赖 .env）
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
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

CMD ["/usr/local/bin/startup.sh"]
