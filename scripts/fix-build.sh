#!/bin/bash
# 解决Docker构建问题的脚本
# 专门解决Composer安装失败的问题

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 打印函数
print_success() { echo -e "${GREEN}[✓] $1${NC}"; }
print_error() { echo -e "${RED}[✗] $1${NC}"; }
print_warning() { echo -e "${YELLOW}[!] $1${NC}"; }
print_info() { echo -e "${BLUE}[i] $1${NC}"; }

# 检查并修复Composer问题
fix_composer_issues() {
    print_info "检查并修复Composer问题..."
    
    # 检查backend目录
    if [ ! -d "backend" ]; then
        print_error "backend目录不存在"
        return 1
    fi
    
    cd backend
    
    # 1. 检查composer.json文件
    if [ ! -f "composer.json" ]; then
        print_error "composer.json文件不存在"
        cd ..
        return 1
    fi
    
    print_success "composer.json文件存在"
    
    # 2. 尝试本地安装依赖（如果Docker构建失败）
    print_info "尝试在本地安装Composer依赖..."
    
    # 检查是否安装了composer
    if command -v composer &> /dev/null; then
        print_info "在本地系统安装依赖..."
        
        # 设置Composer国内镜像
        composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
        
        # 安装依赖
        if composer install --no-dev --optimize-autoloader --no-interaction; then
            print_success "本地Composer依赖安装成功"
            
            # 创建vendor目录的软链接或复制到适当位置
            if [ ! -d "vendor" ]; then
                print_error "vendor目录未创建"
                cd ..
                return 1
            fi
        else
            print_error "本地Composer安装失败"
            cd ..
            return 1
        fi
    else
        print_warning "本地未安装Composer，跳过本地安装"
    fi
    
    cd ..
    return 0
}

# 创建优化后的Dockerfile
create_optimized_dockerfile() {
    print_info "创建优化后的Dockerfile..."
    
    cat > backend/Dockerfile.optimized << 'EOF'
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
    wget \
    netcat-openbsd \
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

# 复制composer.json
COPY composer.json .

# 安装Composer（使用多阶段构建避免网络问题）
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置Composer国内镜像并安装依赖
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ && \
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# 复制应用代码（在依赖安装之后，利用Docker缓存）
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 清理缓存
RUN composer dump-autoload --optimize

# 暴露端口
EXPOSE 9000

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "echo 'Health check passed'; exit(0);" || exit 1

CMD ["php-fpm"]
EOF
    
    print_success "优化后的Dockerfile已创建: backend/Dockerfile.optimized"
    
    # 备份原Dockerfile并替换
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup
        print_info "已备份原Dockerfile"
    fi
    
    cp backend/Dockerfile.optimized backend/Dockerfile
    print_success "已应用优化后的Dockerfile"
}

# 清理Docker缓存
clean_docker_cache() {
    print_info "清理Docker缓存..."
    
    # 停止并删除容器
    docker-compose down 2>/dev/null || true
    
    # 删除未使用的镜像
    docker image prune -f 2>/dev/null || true
    
    # 删除构建缓存
    docker builder prune -f 2>/dev/null || true
    
    # 清理卷（谨慎操作）
    read -p "是否清理Docker卷？这将删除所有数据！(y/N): " clean_volumes
    if [[ "$clean_volumes" == "y" || "$clean_volumes" == "Y" ]]; then
        docker volume prune -f 2>/dev/null || true
        print_warning "已清理Docker卷"
    fi
    
    print_success "Docker缓存清理完成"
}

# 使用国内镜像加速
setup_china_mirrors() {
    print_info "配置国内镜像加速..."
    
    # 创建Docker daemon配置目录
    sudo mkdir -p /etc/docker
    
    # 检查是否已配置镜像加速
    if [ ! -f "/etc/docker/daemon.json" ]; then
        print_info "配置Docker国内镜像加速..."
        
        cat << EOF | sudo tee /etc/docker/daemon.json
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com",
    "https://mirror.ccs.tencentyun.com",
    "https://mirror.baidubce.com"
  ],
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "100m",
    "max-file": "3"
  },
  "storage-driver": "overlay2"
}
EOF
        
        # 重启Docker服务
        if systemctl is-active --quiet docker; then
            sudo systemctl restart docker
            print_success "Docker服务已重启，镜像加速生效"
        fi
    else
        print_info "Docker镜像加速已配置"
    fi
    
    # 创建npm国内镜像配置
    if [ -d "frontend" ]; then
        print_info "配置npm国内镜像..."
        
        cat > frontend/.npmrc << 'EOF'
registry=https://registry.npmmirror.com/
sass_binary_site=https://npm.taobao.org/mirrors/node-sass/
phantomjs_cdnurl=https://npm.taobao.org/mirrors/phantomjs/
electron_mirror=https://npm.taobao.org/mirrors/electron/
chromedriver_cdnurl=https://npm.taobao.org/mirrors/chromedriver/
operadriver_cdnurl=https://npm.taobao.org/mirrors/operadriver/
python_mirror=https://npm.taobao.org/mirrors/python/
EOF
        
        print_success "npm国内镜像配置完成"
    fi
}

# 分步构建
step_by_step_build() {
    print_info "开始分步构建..."
    
    # 步骤1: 只构建基础镜像
    print_info "步骤1: 构建基础PHP镜像..."
    if docker-compose build --no-cache backend; then
        print_success "基础镜像构建成功"
    else
        print_error "基础镜像构建失败"
        return 1
    fi
    
    # 步骤2: 单独运行Composer安装
    print_info "步骤2: 单独运行Composer安装..."
    
    # 创建一个临时容器来运行Composer
    if docker run --rm \
        -v $(pwd)/backend:/app \
        -w /app \
        -e COMPOSER_ALLOW_SUPERUSER=1 \
        -e COMPOSER_MEMORY_LIMIT=-1 \
        composer:latest \
        composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
        && composer install --no-dev --optimize-autoloader --no-interaction; then
        
        print_success "Composer依赖安装成功"
        
        # 步骤3: 重新构建包含vendor的镜像
        print_info "步骤3: 重新构建完整镜像..."
        if docker-compose build backend; then
            print_success "完整镜像构建成功"
        else
            print_error "完整镜像构建失败"
            return 1
        fi
    else
        print_error "Composer安装失败"
        return 1
    fi
    
    return 0
}

# 离线构建模式
offline_build() {
    print_info "准备离线构建..."
    
    # 检查是否有vendor目录
    if [ ! -d "backend/vendor" ]; then
        print_error "vendor目录不存在，无法进行离线构建"
        print_info "请先在线安装依赖或使用分步构建"
        return 1
    fi
    
    print_info "创建离线构建Dockerfile..."
    
    cat > backend/Dockerfile.offline << 'EOF'
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

# 复制整个应用代码（包含vendor目录）
COPY . .

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
    
    # 备份并替换Dockerfile
    if [ -f "backend/Dockerfile" ]; then
        cp backend/Dockerfile backend/Dockerfile.backup.$(date +%Y%m%d_%H%M%S)
    fi
    
    cp backend/Dockerfile.offline backend/Dockerfile
    print_success "离线构建Dockerfile已准备"
    
    # 构建镜像
    print_info "开始离线构建..."
    if docker-compose build backend; then
        print_success "离线构建成功"
        
        # 恢复原Dockerfile
        if [ -f "backend/Dockerfile.backup.$(date +%Y%m%d_%H%M%S)" ]; then
            cp backend/Dockerfile.backup.$(date +%Y%m%d_%H%M%S) backend/Dockerfile
            print_info "已恢复原Dockerfile"
        fi
    else
        print_error "离线构建失败"
        return 1
    fi
    
    return 0
}

# 显示帮助
show_help() {
    echo ""
    echo "Snipe-CN Docker构建问题修复工具"
    echo "========================================"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  --fix-composer     修复Composer相关问题"
    echo "  --optimize-docker  创建优化的Dockerfile"
    echo "  --clean-cache      清理Docker缓存"
    echo "  --china-mirrors    配置国内镜像加速"
    echo "  --step-build       分步构建"
    echo "  --offline-build    离线构建（需要已有vendor目录）"
    echo "  --all              执行所有修复步骤"
    echo "  --help             显示此帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 --fix-composer      # 修复Composer问题"
    echo "  $0 --all               # 执行所有修复"
    echo "  $0 --step-build        # 分步构建镜像"
    echo ""
}

# 主函数
main() {
    echo ""
    echo "========================================"
    echo "  Snipe-CN Docker构建问题修复工具"
    echo "========================================"
    echo ""
    
    # 如果没有参数，显示菜单
    if [ $# -eq 0 ]; then
        echo "请选择修复选项:"
        echo "1. 修复Composer相关问题"
        echo "2. 创建优化的Dockerfile"
        echo "3. 清理Docker缓存"
        echo "4. 配置国内镜像加速"
        echo "5. 分步构建"
        echo "6. 离线构建"
        echo "7. 执行所有修复"
        echo "8. 退出"
        echo ""
        
        read -p "请选择 (1-8): " choice
        
        case $choice in
            1)
                fix_composer_issues
                ;;
            2)
                create_optimized_dockerfile
                ;;
            3)
                clean_docker_cache
                ;;
            4)
                setup_china_mirrors
                ;;
            5)
                step_by_step_build
                ;;
            6)
                offline_build
                ;;
            7)
                fix_composer_issues
                create_optimized_dockerfile
                clean_docker_cache
                setup_china_mirrors
                step_by_step_build
                ;;
            8)
                exit 0
                ;;
            *)
                print_error "无效选择"
                exit 1
                ;;
        esac
    else
        # 处理命令行参数
        case "$1" in
            --fix-composer)
                fix_composer_issues
                ;;
            --optimize-docker)
                create_optimized_dockerfile
                ;;
            --clean-cache)
                clean_docker_cache
                ;;
            --china-mirrors)
                setup_china_mirrors
                ;;
            --step-build)
                step_by_step_build
                ;;
            --offline-build)
                offline_build
                ;;
            --all)
                fix_composer_issues
                create_optimized_dockerfile
                clean_docker_cache
                setup_china_mirrors
                step_by_step_build
                ;;
            --help|*)
                show_help
                ;;
        esac
    fi
    
    echo ""
    print_info "修复完成！"
    echo ""
    echo "下一步操作建议:"
    echo "1. 尝试构建: docker-compose build"
    echo "2. 启动服务: docker-compose up -d"
    echo "3. 查看日志: docker-compose logs -f backend"
    echo ""
    echo "如果仍有问题，请检查:"
    echo "- 网络连接是否正常"
    echo "- Docker服务是否运行"
    echo "- 磁盘空间是否充足"
    echo "- 查看详细错误日志"
    echo ""
    echo "========================================"
}

# 运行主函数
main "$@"