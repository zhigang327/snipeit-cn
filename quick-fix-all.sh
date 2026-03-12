#!/bin/bash
# Snipe-CN快速修复脚本
# 一键解决所有常见部署问题

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

# 显示帮助
show_help() {
    echo ""
    echo "Snipe-CN快速修复工具"
    echo "======================"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  --composer     修复Composer依赖问题"
    echo "  --docker       修复Docker构建问题"
    echo "  --database     修复数据库问题"
    echo "  --permissions  修复文件权限问题"
    echo "  --network      修复网络连接问题"
    echo "  --all          修复所有问题"
    echo "  --help         显示此帮助"
    echo ""
    echo "示例:"
    echo "  $0 --composer    # 修复Composer问题"
    echo "  $0 --all         # 修复所有问题"
    echo ""
}

# 修复Composer问题
fix_composer() {
    log_info "修复Composer依赖问题..."
    
    cd backend 2>/dev/null || {
        log_error "backend目录不存在"
        return 1
    }
    
    # 备份现有vendor目录
    if [ -d "vendor" ]; then
        log_info "备份现有vendor目录..."
        tar -czf vendor-backup-$(date +%Y%m%d_%H%M%S).tar.gz vendor/
        log_success "vendor目录已备份"
    fi
    
    # 检查composer.json
    if [ ! -f "composer.json" ]; then
        log_error "composer.json不存在"
        cd ..
        return 1
    fi
    
    # 设置国内镜像
    log_info "设置Composer国内镜像..."
    composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ 2>/dev/null || true
    
    # 清理缓存
    log_info "清理Composer缓存..."
    composer clear-cache 2>/dev/null || true
    
    # 安装依赖（使用多种方法确保成功）
    log_info "重新安装Composer依赖..."
    
    # 方法1: 标准安装
    if composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs; then
        log_success "Composer依赖安装成功"
    else
        log_warning "第一次安装失败，尝试替代方案..."
        
        # 方法2: 清理后重试
        rm -rf vendor composer.lock 2>/dev/null || true
        composer clear-cache
        
        if composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --prefer-dist; then
            log_success "Composer依赖安装成功（方法2）"
        else
            log_warning "第二次安装失败，尝试最后方案..."
            
            # 方法3: 最小化安装
            if composer install --no-dev --no-interaction --ignore-platform-reqs --no-autoloader; then
                composer dump-autoload --optimize
                log_success "Composer依赖安装成功（方法3）"
            else
                log_error "所有Composer安装方法都失败"
                cd ..
                return 1
            fi
        fi
    fi
    
    cd ..
    log_success "Composer问题修复完成"
    return 0
}

# 修复Docker构建问题
fix_docker() {
    log_info "修复Docker构建问题..."
    
    # 停止现有服务
    log_info "停止现有Docker服务..."
    docker-compose down 2>/dev/null || true
    
    # 清理Docker缓存
    log_info "清理Docker缓存..."
    docker system prune -f 2>/dev/null || true
    docker builder prune -f 2>/dev/null || true
    
    # 检查并修复Dockerfile
    log_info "检查Dockerfile配置..."
    
    if [ -f "backend/Dockerfile.stable" ]; then
        log_info "使用稳定版Dockerfile..."
        cp backend/Dockerfile.stable backend/Dockerfile
    elif [ -f "backend/Dockerfile.production" ]; then
        log_info "使用生产版Dockerfile..."
        cp backend/Dockerfile.production backend/Dockerfile
    else
        log_warning "未找到优化版Dockerfile，使用现有配置"
    fi
    
    # 构建镜像
    log_info "重新构建Docker镜像..."
    
    local max_retries=3
    local retry_count=0
    
    while [ $retry_count -lt $max_retries ]; do
        retry_count=$((retry_count + 1))
        log_info "构建尝试 #$retry_count"
        
        if docker-compose build --no-cache; then
            log_success "Docker镜像构建成功"
            return 0
        fi
        
        log_warning "构建失败，等待10秒后重试..."
        sleep 10
    done
    
    log_error "Docker构建失败，尝试使用简化版..."
    
    # 使用简化版Dockerfile
    cat > backend/Dockerfile.simple-fix << 'EOF'
FROM php:8.2-fpm

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql mbstring bcmath zip

COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json .
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

COPY . .

RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
EOF
    
    cp backend/Dockerfile.simple-fix backend/Dockerfile
    
    if docker-compose build; then
        log_success "使用简化版Dockerfile构建成功"
        return 0
    else
        log_error "所有Docker构建方案都失败"
        return 1
    fi
}

# 修复数据库问题
fix_database() {
    log_info "修复数据库问题..."
    
    # 启动MySQL服务
    log_info "启动MySQL服务..."
    docker-compose up -d mysql 2>/dev/null || {
        log_error "无法启动MySQL服务"
        return 1
    }
    
    # 等待MySQL启动
    log_info "等待MySQL启动..."
    sleep 30
    
    # 检查MySQL连接
    if ! docker-compose exec -T mysql mysqladmin ping -h localhost -u root -p$DB_ROOT_PASSWORD 2>/dev/null; then
        log_error "MySQL连接失败"
        return 1
    fi
    
    # 检查数据库是否存在
    log_info "检查数据库状态..."
    
    if docker-compose exec -T mysql mysql -u root -p$DB_ROOT_PASSWORD -e "SHOW DATABASES;" 2>/dev/null | grep -q "$DB_DATABASE"; then
        log_info "数据库已存在"
        
        # 备份现有数据库
        log_info "备份现有数据库..."
        docker-compose exec -T mysql mysqldump -u root -p$DB_ROOT_PASSWORD $DB_DATABASE > db-backup-$(date +%Y%m%d_%H%M%S).sql 2>/dev/null || true
        log_success "数据库已备份"
    else
        log_info "创建数据库..."
        docker-compose exec -T mysql mysql -u root -p$DB_ROOT_PASSWORD -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE; GRANT ALL PRIVILEGES ON $DB_DATABASE.* TO '$DB_USERNAME'@'%'; FLUSH PRIVILEGES;" 2>/dev/null
        log_success "数据库创建完成"
    fi
    
    # 运行数据库迁移
    log_info "运行数据库迁移..."
    
    if docker-compose exec backend php artisan migrate --force 2>/dev/null; then
        log_success "数据库迁移成功"
    else
        log_warning "数据库迁移失败，尝试修复..."
        
        # 尝试修复迁移
        docker-compose exec backend php artisan migrate:fresh --force --seed 2>/dev/null && \
        log_success "数据库迁移修复成功" || \
        log_error "数据库迁移修复失败"
    fi
    
    log_success "数据库问题修复完成"
    return 0
}

# 修复文件权限问题
fix_permissions() {
    log_info "修复文件权限问题..."
    
    # 修复后端文件权限
    if [ -d "backend" ]; then
        log_info "修复backend目录权限..."
        
        cd backend
        mkdir -p storage bootstrap/cache 2>/dev/null || true
        
        # 设置正确的所有权
        sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
        chown -R 1000:1000 storage bootstrap/cache 2>/dev/null || true
        
        # 设置正确的权限
        chmod -R 775 storage bootstrap/cache 2>/dev/null || true
        chmod -R 777 storage/logs 2>/dev/null || true
        
        cd ..
        log_success "backend目录权限修复完成"
    fi
    
    # 修复前端文件权限
    if [ -d "frontend" ]; then
        log_info "修复frontend目录权限..."
        
        cd frontend
        chmod -R 755 . 2>/dev/null || true
        
        cd ..
        log_success "frontend目录权限修复完成"
    fi
    
    # 修复Docker相关文件权限
    log_info "修复Docker相关文件权限..."
    chmod +x *.sh scripts/*.sh 2>/dev/null || true
    
    log_success "文件权限修复完成"
    return 0
}

# 修复网络连接问题
fix_network() {
    log_info "修复网络连接问题..."
    
    # 检查网络配置
    log_info "检查Docker网络..."
    
    if ! docker network ls | grep -q "snipe-cn"; then
        log_info "创建Docker网络..."
        docker network create snipe-cn-network 2>/dev/null || true
    fi
    
    # 检查端口占用
    log_info "检查端口占用情况..."
    
    ports=("80" "8000" "3306" "6379" "5173")
    for port in "${ports[@]}"; do
        if lsof -i :$port >/dev/null 2>&1; then
            log_warning "端口 $port 被占用"
            
            # 建议修改端口
            if [ "$port" = "80" ]; then
                log_info "建议修改NGINX_PORT为8080"
                sed -i "s/NGINX_PORT=80/NGINX_PORT=8080/" .env 2>/dev/null || true
            fi
        else
            log_success "端口 $port 可用"
        fi
    done
    
    # 检查服务间网络连通性
    log_info "检查服务间网络连通性..."
    
    # 启动基础服务
    docker-compose up -d mysql redis 2>/dev/null || true
    sleep 10
    
    # 测试MySQL到Redis的网络
    if docker-compose exec mysql ping -c 1 redis 2>/dev/null; then
        log_success "MySQL到Redis网络连通正常"
    else
        log_warning "MySQL到Redis网络连通异常"
    fi
    
    log_success "网络连接修复完成"
    return 0
}

# 修复所有问题
fix_all() {
    log_info "开始修复所有问题..."
    
    # 加载环境变量
    if [ -f ".env" ]; then
        source .env 2>/dev/null || true
    fi
    
    # 执行修复步骤
    local success_count=0
    local total_steps=5
    
    echo ""
    echo "修复进度:"
    echo "----------------------------------------"
    
    # 步骤1: 文件权限
    if fix_permissions; then
        log_success "[1/$total_steps] 文件权限修复完成"
        ((success_count++))
    else
        log_error "[1/$total_steps] 文件权限修复失败"
    fi
    
    # 步骤2: Composer依赖
    if fix_composer; then
        log_success "[2/$total_steps] Composer依赖修复完成"
        ((success_count++))
    else
        log_error "[2/$total_steps] Composer依赖修复失败"
    fi
    
    # 步骤3: Docker构建
    if fix_docker; then
        log_success "[3/$total_steps] Docker构建修复完成"
        ((success_count++))
    else
        log_error "[3/$total_steps] Docker构建修复失败"
    fi
    
    # 步骤4: 数据库
    if fix_database; then
        log_success "[4/$total_steps] 数据库修复完成"
        ((success_count++))
    else
        log_error "[4/$total_steps] 数据库修复失败"
    fi
    
    # 步骤5: 网络连接
    if fix_network; then
        log_success "[5/$total_steps] 网络连接修复完成"
        ((success_count++))
    else
        log_error "[5/$total_steps] 网络连接修复失败"
    fi
    
    echo "----------------------------------------"
    echo ""
    
    if [ $success_count -eq $total_steps ]; then
        log_success "✅ 所有问题修复完成 ($success_count/$total_steps)"
        
        # 启动所有服务
        log_info "启动所有服务..."
        if docker-compose up -d; then
            log_success "服务启动成功"
            
            # 等待服务就绪
            sleep 30
            
            # 运行测试
            log_info "运行部署测试..."
            if [ -f "test-deployment.sh" ]; then
                chmod +x test-deployment.sh
                ./test-deployment.sh
            fi
        else
            log_error "服务启动失败"
        fi
    else
        log_warning "⚠️  部分问题修复完成 ($success_count/$total_steps)"
        log_info "建议手动检查未修复的问题"
    fi
    
    return 0
}

# 主函数
main() {
    echo ""
    echo "========================================"
    echo "  Snipe-CN快速修复工具"
    echo "========================================"
    echo ""
    
    # 检查是否在项目目录
    if [ ! -f "docker-compose.yml" ]; then
        log_error "未在项目根目录，请在snipeit-cn目录下运行"
        exit 1
    fi
    
    # 如果没有参数，显示菜单
    if [ $# -eq 0 ]; then
        echo "请选择修复选项:"
        echo "1. 修复Composer依赖问题"
        echo "2. 修复Docker构建问题"
        echo "3. 修复数据库问题"
        echo "4. 修复文件权限问题"
        echo "5. 修复网络连接问题"
        echo "6. 修复所有问题"
        echo "7. 退出"
        echo ""
        
        read -p "请选择 (1-7): " choice
        
        case $choice in
            1) fix_composer ;;
            2) fix_docker ;;
            3) fix_database ;;
            4) fix_permissions ;;
            5) fix_network ;;
            6) fix_all ;;
            7) exit 0 ;;
            *) log_error "无效选择"; exit 1 ;;
        esac
    else
        # 处理命令行参数
        case "$1" in
            --composer) fix_composer ;;
            --docker) fix_docker ;;
            --database) fix_database ;;
            --permissions) fix_permissions ;;
            --network) fix_network ;;
            --all) fix_all ;;
            --help|*) show_help ;;
        esac
    fi
    
    echo ""
    echo "========================================"
    echo "修复完成！"
    echo "========================================"
    echo ""
    
    # 显示下一步建议
    log_info "下一步建议:"
    echo "1. 运行部署测试: ./test-deployment.sh"
    echo "2. 查看服务状态: docker-compose ps"
    echo "3. 查看服务日志: docker-compose logs -f"
    echo "4. 访问Web界面: http://localhost"
    echo ""
}

# 执行主函数
main "$@"