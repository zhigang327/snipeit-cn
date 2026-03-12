FROM php:8.2-fpm

# 设置时区为中国时区
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libssl-dev \
    wget \
    && rm -rf /var/lib/apt/lists/*

# 安装PHP扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# 安装Redis扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装Composer (使用国内镜像加速)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置Composer国内镜像
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 设置工作目录
WORKDIR /var/www/html

# 复制composer.json和composer.lock文件
COPY composer.json composer.lock ./

# 安装依赖（使用--no-interaction避免交互提示）
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    || (composer clear-cache && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts)

# 复制应用代码
COPY . .

# 生成应用密钥（如果不存在）
RUN if [ ! -f .env ]; then \
        cp .env.example .env && \
        php artisan key:generate; \
    fi

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 清理缓存
RUN composer dump-autoload --optimize \
    && php artisan config:clear \
    && php artisan cache:clear

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
