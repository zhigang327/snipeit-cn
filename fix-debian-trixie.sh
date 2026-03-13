#!/bin/bash
# 快速修复Debian Trixie等新版本的包兼容性问题

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 日志函数
log_info() { echo -e "${BLUE}[INFO] $1${NC}"; }
log_success() { echo -e "${GREEN}[SUCCESS] $1${NC}"; }
log_warning() { echo -e "${YELLOW}[WARNING] $1${NC}"; }
log_error() { echo -e "${RED}[ERROR] $1${NC}"; }

# 主修复函数
main() {
    echo ""
    echo "========================================"
    echo "  Debian Trixie包兼容性修复工具"
    echo "========================================"
    echo ""
    
    # 检测系统
    log_info "检测系统版本..."
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        log_info "系统: $ID $VERSION_ID ($VERSION_CODENAME)"
    fi
    
    # 检查错误
    log_info "检查常见问题..."
    
    # 问题1: libc-client-dev 包不存在
    if apt-cache show libc-client-dev 2>/dev/null | grep -q "Package: libc-client-dev"; then
        log_success "libc-client-dev 包可用"
    else
        log_warning "libc-client-dev 包不可用，寻找替代方案..."
        
        # 检查可能的替代包
        echo "可能替代方案:"
        apt-cache search "c-client" | head -5 || true
        apt-cache search "imap" | grep -i "dev" | head -5 || true
    fi
    
    # 创建修复后的Dockerfile
    log_info "创建兼容性修复的Dockerfile..."
    
    cat > backend/Dockerfile.compatible << 'EOF'
# Snipe-CN兼容版Dockerfile
# 专为Debian Trixie等新版本设计
# 移除不存在的包，使用兼容包名

FROM php:8.2-fpm

# 基础配置
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 系统依赖安装（使用兼容包名）
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype-dev \
    libjpeg-dev \
    libwebp-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    libgmp-dev \
    libldap2-dev \
    libicu-dev \
    libpq-dev \
    libsqlite3-dev \
    zlib1g-dev \
    libbz2-dev \
    libreadline-dev \
    libxslt-dev \
    zip \
    unzip \
    g++ \
    make \
    pkg-config \
    autoconf \
    && rm -rf /var/lib/apt/lists/*

# PHP扩展安装
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    mysqli \
    bcmath \
    mbstring \
    exif \
    pcntl \
    zip \
    opcache \
    intl \
    gd \
    soap \
    sockets

# 安装Composer
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 复制composer配置
COPY composer.json composer.lock ./

# 设置Composer国内镜像
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 安装依赖
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

# 复制应用代码
COPY . .

# 设置权限
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
EOF
    
    log_success "兼容版Dockerfile创建完成: backend/Dockerfile.compatible"
    
    # 应用修复
    log_info "应用修复..."
    
    # 备份原Dockerfile
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%Y%m%d_%H%M%S)
        log_success "已备份原Dockerfile"
    fi
    
    # 使用兼容版
    cp backend/Dockerfile.compatible backend/Dockerfile
    log_success "已应用兼容版Dockerfile"
    
    # 清理Docker缓存
    log_info "清理Docker缓存..."
    docker-compose down 2>/dev/null || true
    docker system prune -f 2>/dev/null || true
    
    # 尝试构建
    log_info "尝试重新构建..."
    echo ""
    
    read -p "是否立即尝试构建？(y/N): " try_build
    if [[ "$try_build" == "y" || "$try_build" == "Y" ]]; then
        if docker-compose build --no-cache; then
            log_success "构建成功！"
            
            # 启动服务
            read -p "是否启动服务？(y/N): " start_services
            if [[ "$start_services" == "y" || "$start_services" == "Y" ]]; then
                docker-compose up -d
                log_success "服务已启动"
            fi
        else
            log_error "构建失败，尝试其他方案..."
            
            # 尝试使用最小化版本
            echo ""
            log_info "尝试使用最小化版本..."
            if [ -f "backend/Dockerfile.minimal" ]; then
                cp backend/Dockerfile.minimal backend/Dockerfile
                if docker-compose build; then
                    log_success "使用最小化版本构建成功"
                else
                    log_error "所有方案均失败，请查看详细错误日志"
                fi
            fi
        fi
    fi
    
    echo ""
    echo "========================================"
    echo "修复完成！"
    echo "========================================"
    echo ""
    
    log_info "下一步建议:"
    echo "1. 如果构建成功: docker-compose up -d"
    echo "2. 如果仍然失败: 查看详细错误日志"
    echo "3. 恢复原配置: cp backend/Dockerfile.backup.* backend/Dockerfile"
    echo ""
    
    log_info "可用的Dockerfile版本:"
    ls -la backend/Dockerfile*
    echo ""
}

# 执行主函数
main "$@"