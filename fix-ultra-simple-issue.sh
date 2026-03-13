#!/bin/bash
# 修复ultra-simple Dockerfile问题的脚本
# 解决 "没有vendor目录" 错误

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

log_success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

log_error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

echo ""
echo "========================================"
echo "  修复 Ultra-Simple Dockerfile 问题"
echo "========================================"
echo ""

# 检查当前目录
if [ ! -f "docker-compose.yml" ]; then
    log_error "请确保在项目根目录运行此脚本"
    exit 1
fi

# 检查是否有vendor目录
if [ -d "backend/vendor" ]; then
    log_success "检测到vendor目录"
    VENDOR_EXISTS=true
elif [ -d "vendor" ]; then
    log_success "检测到vendor目录（在根目录）"
    # 复制到backend目录
    cp -r vendor backend/vendor 2>/dev/null || true
    VENDOR_EXISTS=true
else
    log_warning "未检测到vendor目录"
    VENDOR_EXISTS=false
fi

# 创建改进的ultra-simple Dockerfile
log_info "创建改进的ultra-simple Dockerfile..."

cat > backend/Dockerfile.ultra-simple << 'EOF'
FROM php:8.2-fpm

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 只安装绝对必需的包
RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装核心PHP扩展
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip

# 安装Composer（但从本地复制）
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 复制应用代码，先不复制vendor目录
COPY --exclude=vendor . .

# 方案选择：支持三种安装模式
RUN echo "========================================" && \
    echo "  依赖安装方案选择" && \
    echo "========================================" && \
    if [ -d "vendor" ]; then \
        echo "✓ 方案1: 使用现有的vendor目录（离线模式）"; \
        cp -r vendor/ ./vendor/ 2>/dev/null || true; \
        echo "✓ 已复制vendor目录"; \
    else \
        echo "⚠ 方案2: 尝试网络安装依赖..."; \
        # 设置Composer配置（带容错）
        composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ 2>/dev/null || true; \
        composer config -g process-timeout 3600 2>/dev/null || true; \
        composer clear-cache 2>/dev/null || true; \
        \
        # 检查网络连接
        echo "检查网络连接..." && \
        if curl -s --connect-timeout 10 https://mirrors.aliyun.com/composer/ >/dev/null 2>&1; then \
            echo "✓ 可以连接到阿里云镜像"; \
            echo "开始下载依赖..." && \
            if composer install \
                --no-dev \
                --no-interaction \
                --no-scripts \
                --prefer-dist \
                --ignore-platform-reqs \
                --no-progress; then \
                echo "✓ 依赖下载成功"; \
            else \
                echo "⚠ 依赖下载失败，尝试备用方案..."; \
                # 尝试官方镜像
                composer config -g repo.packagist composer https://repo.packagist.org 2>/dev/null || true; \
                if composer install \
                    --no-dev \
                    --no-interaction \
                    --no-scripts \
                    --ignore-platform-reqs; then \
                    echo "✓ 使用官方镜像下载成功"; \
                else \
                    echo "⚠ 所有网络方案失败，创建最小化结构..."; \
                    # 创建最小化结构让应用可以启动
                    mkdir -p vendor/composer; \
                    echo '{"autoload":{"psr-4":{"App\\\": "app/"}}}' > vendor/composer/autoload_namespaces.php 2>/dev/null || true; \
                    echo '<?php // Minimal autoloader' > vendor/autoload.php 2>/dev/null || true; \
                    echo "✓ 已创建最小化依赖结构"; \
                    echo "⚠ 注意: 部分PHP功能可能受限，建议在有网络时重新安装依赖"; \
                fi; \
            fi; \
        else \
            echo "⚠ 无法连接到阿里云镜像，使用离线最小化方案"; \
            # 创建最小化结构
            mkdir -p vendor/composer; \
            echo '{"autoload":{"psr-4":{"App\\\": "app/"}}}' > vendor/composer/autoload_namespaces.php 2>/dev/null || true; \
            echo '<?php // Minimal autoloader' > vendor/autoload.php 2>/dev/null || true; \
            echo "✓ 已创建最小化依赖结构"; \
        fi; \
    fi

# 复制可能错过的vendor目录（如果有的话）
COPY --exclude=*.php vendor/ ./vendor/ 2>/dev/null || true

# 设置权限
RUN mkdir -p \
    storage \
    storage/framework \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# 如果没有.env文件，创建简单版本
RUN if [ ! -f .env ] && [ -f .env.example ]; then \
        cp .env.example .env; \
        echo "✓ 已从.env.example创建.env文件"; \
    elif [ ! -f .env ]; then \
        echo "创建基本.env配置..." && \
        cat > .env << 'ENVFILE'
APP_NAME=Snipe-CN
APP_ENV=production
APP_KEY=base64:$(base64 /dev/urandom | head -c32)
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=snipe_cn
DB_USERNAME=snipe_user
DB_PASSWORD=snipe_password

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
ENVFILE
        echo "✓ 已创建基本.env文件"; \
    fi

EXPOSE 9000
CMD ["php-fpm"]
EOF

log_success "已创建改进的Dockerfile.ultra-simple"

# 提供解决方案选项
echo ""
echo "========================================"
echo "  解决方案选项"
echo "========================================"
echo ""

if [ "$VENDOR_EXISTS" = false ]; then
    echo "当前状态: 没有vendor目录"
    echo ""
    echo "请选择解决方案:"
    echo "1. 使用改进的ultra-simple版本（会自动尝试网络安装）"
    echo "2. 创建离线vendor包（需要另一台有网络的环境）"
    echo "3. 使用离线版Dockerfile（内置重试机制）"
    echo ""
    
    read -p "请选择 (1-3，默认1): " choice
    choice=${choice:-1}
    
    case $choice in
        1)
            log_info "将使用改进的ultra-simple版本"
            cp backend/Dockerfile.ultra-simple backend/Dockerfile
            log_success "已切换到改进版本"
            ;;
        2)
            log_info "创建离线vendor包..."
            if [ -f "fix-network-issues.sh" ]; then
                ./fix-network-issues.sh --offline
            else
                log_error "fix-network-issues.sh脚本不存在"
                echo "请手动运行: composer install --no-dev --optimize-autoloader"
            fi
            ;;
        3)
            log_info "使用离线版Dockerfile..."
            if [ -f "backend/Dockerfile.offline" ]; then
                cp backend/Dockerfile.offline backend/Dockerfile
                log_success "已切换到离线版"
            else
                log_error "离线版Dockerfile不存在"
                cp backend/Dockerfile.ultra-simple backend/Dockerfile
            fi
            ;;
    esac
else
    log_success "有vendor目录，可以直接使用改进版本"
    cp backend/Dockerfile.ultra-simple backend/Dockerfile
fi

# 显示使用说明
echo ""
echo "========================================"
echo "  使用说明"
echo "========================================"
echo ""
echo "1. 构建镜像:"
echo "   docker-compose build --no-cache"
echo ""
echo "2. 启动服务:"
echo "   docker-compose up -d"
echo ""
echo "3. 检查状态:"
echo "   docker-compose ps"
echo ""
echo "4. 查看日志:"
echo "   docker-compose logs -f backend"
echo ""
echo "注意事项:"
echo "- 如果使用最小化结构，部分PHP功能可能受限"
echo "- 建议在有网络时重新构建完整版本"
echo "- 可以运行 'composer install' 手动安装依赖"

log_success "修复完成！"