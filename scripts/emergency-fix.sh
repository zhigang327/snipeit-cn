#!/bin/bash
# 紧急修复脚本 - 专门解决Composer exit code 2错误

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}[✓] $1${NC}"; }
print_error() { echo -e "${RED}[✗] $1${NC}"; }
print_warning() { echo -e "${YELLOW}[!] $1${NC}"; }
print_info() { echo -e "${BLUE}[i] $1${NC}"; }

# 显示横幅
show_banner() {
    echo ""
    echo "========================================"
    echo "    Composer紧急修复工具"
    echo "    专门解决exit code 2错误"
    echo "========================================"
    echo ""
}

# 诊断问题
diagnose_issue() {
    print_info "诊断Composer安装问题..."
    
    # 检查composer.json
    if [ ! -f "backend/composer.json" ]; then
        print_error "backend/composer.json不存在"
        return 1
    fi
    
    print_success "composer.json文件存在"
    
    # 检查PHP版本要求
    PHP_REQUIRED=$(grep -o '"php": "[^"]*"' backend/composer.json | cut -d'"' -f4)
    if [ -n "$PHP_REQUIRED" ]; then
        print_info "PHP版本要求: $PHP_REQUIRED"
    fi
    
    # 检查依赖包
    print_info "检查依赖包..."
    grep -A 20 '"require"' backend/composer.json | head -25
    
    return 0
}

# 方案1: 使用多阶段构建避免网络问题
solution_multistage() {
    print_info "方案1: 使用多阶段构建..."
    
    cat > backend/Dockerfile.multistage << 'EOF'
# 第一阶段: 构建阶段
FROM composer:2.6 AS composer-builder

# 设置工作目录
WORKDIR /app

# 复制composer文件
COPY backend/composer.json .

# 设置国内镜像
RUN composer config repo.packagist composer https://mirrors.aliyun.com/composer/

# 安装依赖到vendor目录
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# 第二阶段: 运行阶段
FROM php:8.2-fpm

# 设置时区
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

# 设置工作目录
WORKDIR /var/www/html

# 从构建阶段复制vendor目录
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# 复制应用代码
COPY backend .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 优化自动加载
RUN composer dump-autoload --optimize

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
EOF
    
    print_success "多阶段Dockerfile已创建"
    
    # 备份原Dockerfile
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%s)
        print_info "已备份原Dockerfile"
    fi
    
    # 使用多阶段Dockerfile
    cp backend/Dockerfile.multistage backend/Dockerfile
    
    print_info "开始构建多阶段镜像..."
    if docker-compose build backend; then
        print_success "多阶段构建成功！"
        return 0
    else
        print_error "多阶段构建失败"
        return 1
    fi
}

# 方案2: 完全跳过Composer安装，使用预构建的vendor
solution_prebuilt() {
    print_info "方案2: 使用预构建的vendor目录..."
    
    # 检查是否有vendor目录
    if [ -d "backend/vendor" ]; then
        print_success "发现现有的vendor目录"
    else
        print_warning "没有vendor目录，尝试在线安装..."
        
        # 在主机上安装依赖
        if command -v composer &> /dev/null; then
            cd backend
            print_info "在主机上安装Composer依赖..."
            
            # 设置镜像
            composer config repo.packagist composer https://mirrors.aliyun.com/composer/
            
            if composer install --no-dev --optimize-autoloader --no-interaction; then
                print_success "主机安装成功"
                cd ..
            else
                print_error "主机安装失败"
                cd ..
                return 1
            fi
        else
            print_error "主机未安装Composer"
            return 1
        fi
    fi
    
    # 创建跳过Composer安装的Dockerfile
    cat > backend/Dockerfile.nocomposer << 'EOF'
FROM php:8.2-fpm

# 设置时区
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

# 设置工作目录
WORKDIR /var/www/html

# 复制整个应用（包含vendor目录）
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 优化自动加载
RUN php -r "require 'vendor/autoload.php'; echo 'Autoload optimized';"

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
EOF
    
    # 备份并替换
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%s)
    fi
    
    cp backend/Dockerfile.nocomposer backend/Dockerfile
    
    print_info "开始构建（跳过Composer安装）..."
    if docker-compose build backend; then
        print_success "跳过Composer安装构建成功！"
        return 0
    else
        print_error "构建失败"
        return 1
    fi
}

# 方案3: 使用Alpine基础镜像
solution_alpine() {
    print_info "方案3: 使用Alpine Linux基础镜像..."
    
    cat > backend/Dockerfile.alpine << 'EOF'
FROM php:8.2-fpm-alpine

# 设置时区
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 安装系统依赖
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    freetype-dev \
    jpeg-dev \
    libwebp-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    openssl-dev \
    $PHPIZE_DEPS

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

# 安装Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 设置工作目录
WORKDIR /var/www/html

# 复制composer文件
COPY composer.json .

# 设置Composer镜像（不设置全局，避免权限问题）
RUN composer config repo.packagist composer https://mirrors.aliyun.com/composer/

# 安装依赖
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# 复制应用代码
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage \
    && chmod -R 755 bootstrap/cache

# 优化自动加载
RUN composer dump-autoload --optimize

# 清理
RUN apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
EOF
    
    # 备份并替换
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%s)
    fi
    
    cp backend/Dockerfile.alpine backend/Dockerfile
    
    print_info "开始构建Alpine镜像..."
    if docker-compose build backend; then
        print_success "Alpine镜像构建成功！"
        return 0
    else
        print_error "Alpine镜像构建失败"
        return 1
    fi
}

# 方案4: 使用最小的基础镜像
solution_minimal() {
    print_info "方案4: 使用最小化配置..."
    
    # 创建最简单的Dockerfile
    cat > backend/Dockerfile.minimal << 'EOF'
FROM php:8.2-cli

# 仅安装必要扩展
RUN docker-php-ext-install pdo pdo_mysql

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 复制代码
COPY . .

# 离线安装依赖（假设vendor已存在）
# 如果没有vendor，需要先在其他地方安装

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
EOF
    
    print_warning "最小化方案仅用于紧急情况，功能可能受限"
    
    # 询问是否继续
    read -p "继续使用最小化方案？(y/N): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        print_info "已取消最小化方案"
        return 1
    fi
    
    # 备份并替换
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%s)
    fi
    
    cp backend/Dockerfile.minimal backend/Dockerfile
    
    # 修改docker-compose.yml使用不同的命令
    if [ -f "docker-compose.yml" ]; then
        cp docker-compose.yml docker-compose.yml.backup.$(date +%s)
        
        # 临时修改backend服务配置
        sed -i '' 's|command: php artisan serve --host=0.0.0.0 --port=8000|command: php -S 0.0.0.0:8000 -t public|' docker-compose.yml
        sed -i '' 's|php-fpm|php -S 0.0.0.0:8000 -t public|' backend/Dockerfile.minimal
    fi
    
    print_info "开始最小化构建..."
    if docker-compose build backend; then
        print_success "最小化构建成功！"
        print_warning "注意：这是临时解决方案，建议后续使用完整构建"
        return 0
    else
        print_error "最小化构建失败"
        return 1
    fi
}

# 恢复原始配置
restore_original() {
    print_info "恢复原始配置..."
    
    # 恢复Dockerfile
    local backups=($(ls -t backend/Dockerfile.backup.* 2>/dev/null))
    if [ ${#backups[@]} -gt 0 ]; then
        cp "${backups[0]}" backend/Dockerfile
        print_success "已恢复Dockerfile"
    else
        print_warning "未找到Dockerfile备份"
    fi
    
    # 恢复docker-compose.yml
    if [ -f "docker-compose.yml.backup" ]; then
        cp docker-compose.yml.backup docker-compose.yml
        print_success "已恢复docker-compose.yml"
    fi
    
    # 恢复docker-compose.yml的特定备份
    local compose_backups=($(ls -t docker-compose.yml.backup.* 2>/dev/null))
    if [ ${#compose_backups[@]} -gt 0 ]; then
        cp "${compose_backups[0]}" docker-compose.yml
        print_success "已恢复docker-compose.yml"
    fi
}

# 显示菜单
show_menu() {
    echo ""
    echo "请选择修复方案:"
    echo "1. 多阶段构建（推荐，最稳定）"
    echo "2. 预构建vendor目录（需要网络）"
    echo "3. Alpine Linux镜像（轻量级）"
    echo "4. 最小化配置（紧急情况）"
    echo "5. 诊断问题"
    echo "6. 恢复原始配置"
    echo "7. 退出"
    echo ""
}

# 主函数
main() {
    show_banner
    
    # 检查是否在项目目录
    if [ ! -f "docker-compose.yml" ]; then
        print_error "请在项目根目录运行此脚本"
        exit 1
    fi
    
    # 创建备份目录
    mkdir -p .backups
    
    while true; do
        show_menu
        read -p "请选择 (1-7): " choice
        
        case $choice in
            1)
                if solution_multistage; then
                    print_success "✅ 问题已解决！可以启动服务了。"
                    echo ""
                    echo "启动服务: docker-compose up -d"
                    echo "查看状态: docker-compose ps"
                    echo "查看日志: docker-compose logs -f backend"
                    break
                fi
                ;;
            2)
                if solution_prebuilt; then
                    print_success "✅ 问题已解决！可以启动服务了。"
                    break
                fi
                ;;
            3)
                if solution_alpine; then
                    print_success "✅ 问题已解决！可以启动服务了。"
                    break
                fi
                ;;
            4)
                if solution_minimal; then
                    print_success "✅ 紧急方案已启用！可以启动服务了。"
                    print_warning "⚠️  这是临时解决方案，建议后续使用方案1"
                    break
                fi
                ;;
            5)
                diagnose_issue
                ;;
            6)
                restore_original
                print_success "已恢复原始配置"
                ;;
            7)
                print_info "退出修复工具"
                exit 0
                ;;
            *)
                print_error "无效选择"
                ;;
        esac
        
        echo ""
        read -p "按回车键继续..."
    done
    
    echo ""
    echo "========================================"
    echo "    修复完成！"
    echo "========================================"
    echo ""
    echo "下一步操作:"
    echo "1. 启动服务: docker-compose up -d"
    echo "2. 初始化数据库: docker-compose exec backend php artisan migrate --force"
    echo "3. 填充数据: docker-compose exec backend php artisan db:seed --force"
    echo "4. 访问系统: http://localhost"
    echo ""
    echo "如果仍有问题，请提供详细的错误信息。"
    echo "========================================"
}

# 运行主函数
main "$@"