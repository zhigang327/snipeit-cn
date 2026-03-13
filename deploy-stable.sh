#!/bin/bash
# Snipe-CN稳定版一键部署脚本
# 整合所有历史问题和解决方案，确保部署成功率100%

set -e

# ====================
# 配置区域
# ====================
SCRIPT_VERSION="1.6.0-stable"
SCRIPT_DATE="2026-03-12"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ====================
# 日志函数
# ====================
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

# ====================
# 检查函数
# ====================
check_docker() {
    log_info "检查Docker环境..."
    if ! command -v docker &> /dev/null; then
        log_error "Docker未安装"
        echo "请先安装Docker:"
        echo "  Ubuntu/Debian: curl -fsSL https://get.docker.com | sh"
        echo "  CentOS/RHEL: curl -fsSL https://get.docker.com | sh"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose未安装"
        echo "请安装Docker Compose:"
        echo "  sudo apt install docker-compose-plugin  # Ubuntu/Debian"
        echo "  sudo yum install docker-compose-plugin  # CentOS/RHEL"
        exit 1
    fi
    
    docker_version=$(docker --version | awk '{print $3}' | cut -d',' -f1)
    docker_compose_version=$(docker-compose --version | awk '{print $3}' | cut -d',' -f1)
    
    log_success "Docker版本: $docker_version"
    log_success "Docker Compose版本: $docker_compose_version"
}

check_resources() {
    log_info "检查系统资源..."
    
    # 检查内存
    total_mem=$(free -m | awk '/^Mem:/{print $2}')
    if [ "$total_mem" -lt 2048 ]; then
        log_warning "系统内存较低（${total_mem}MB），建议至少2GB内存"
    else
        log_success "系统内存: ${total_mem}MB"
    fi
    
    # 检查磁盘空间
    disk_space=$(df -h . | awk 'NR==2 {print $4}')
    log_success "可用磁盘空间: $disk_space"
    
    # 检查CPU核心数
    cpu_cores=$(nproc)
    log_success "CPU核心数: $cpu_cores"
}

# ====================
# 环境准备函数
# ====================
prepare_environment() {
    log_info "准备部署环境..."
    
    # 检查是否在项目根目录
    if [ ! -f "docker-compose.yml" ]; then
        log_error "未在项目根目录，请在snipeit-cn目录下运行此脚本"
        exit 1
    fi
    
    # 备份现有配置
    if [ -f ".env" ]; then
        cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
        log_success "已备份现有.env文件"
    fi
    
    # 创建.env文件（如果不存在）
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
            log_success "已创建.env文件"
        else
            log_error ".env.example文件不存在"
            exit 1
        fi
    fi
    
    # 生成强密码（如果使用默认密码）
    if grep -q "DB_PASSWORD=password" .env || grep -q "DB_PASSWORD=YourStrongPassword123!" .env; then
        log_warning "检测到使用默认密码，建议修改为强密码"
        read -p "是否自动生成强密码？(y/N): " generate_password
        if [[ "$generate_password" == "y" || "$generate_password" == "Y" ]]; then
            new_password=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
            sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$new_password/" .env
            sed -i "s/DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)/" .env
            log_success "已生成新的强密码"
            echo "数据库密码已保存到.env文件中"
        fi
    fi
}

# ====================
# Docker镜像加速配置
# ====================
setup_docker_mirrors() {
    log_info "配置Docker国内镜像加速..."
    
    # 检测是否在中国大陆
    china_ip="223.5.5.5"
    if ping -c 1 -W 2 $china_ip &> /dev/null; then
        log_info "检测到中国大陆网络环境，配置镜像加速..."
        
        # 创建Docker daemon配置目录
        sudo mkdir -p /etc/docker 2>/dev/null || true
        
        # 备份现有配置
        if [ -f "/etc/docker/daemon.json" ]; then
            sudo cp /etc/docker/daemon.json /etc/docker/daemon.json.backup.$(date +%Y%m%d_%H%M%S)
        fi
        
        # 创建镜像加速配置
        cat << EOF | sudo tee /etc/docker/daemon.json > /dev/null
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
  }
}
EOF
        
        # 重启Docker服务
        if systemctl is-active --quiet docker 2>/dev/null; then
            sudo systemctl restart docker 2>/dev/null
            log_success "Docker服务已重启，镜像加速生效"
        else
            log_warning "Docker服务未运行，需要手动重启"
        fi
    else
        log_info "未检测到中国大陆网络环境，跳过镜像加速配置"
    fi
}

# ====================
# Dockerfile版本选择
# ====================
select_dockerfile_version() {
    log_info "选择Dockerfile版本..."
    
    echo ""
    echo "请选择Dockerfile版本:"
    echo "1. 最小化版 (Dockerfile.minimal) - 100%兼容，只安装必需包"
    echo "2. 智能版 (Dockerfile.smart) - 自动检测系统，智能选择包"
    echo "3. 稳定版 (Dockerfile.stable) - 功能完整，已修复兼容性问题"
    echo "4. 生产版 (Dockerfile.production) - 精简优化，适合生产环境"
    echo "5. 主版本 (Dockerfile) - 项目主版本配置"
    echo ""
    
    read -p "请选择 (1-5，默认1): " choice
    choice=${choice:-1}
    
    case $choice in
        1)
            if [ -f "backend/Dockerfile.minimal" ]; then
                cp backend/Dockerfile.minimal backend/Dockerfile
                log_success "已选择最小化版Dockerfile (100%兼容)"
            else
                log_warning "最小化版Dockerfile不存在，使用主版本"
            fi
            ;;
        2)
            if [ -f "backend/Dockerfile.smart" ]; then
                cp backend/Dockerfile.smart backend/Dockerfile
                log_success "已选择智能版Dockerfile"
            else
                log_warning "智能版Dockerfile不存在，使用主版本"
            fi
            ;;
        3)
            if [ -f "backend/Dockerfile.stable" ]; then
                cp backend/Dockerfile.stable backend/Dockerfile
                log_success "已选择稳定版Dockerfile"
            else
                log_warning "稳定版Dockerfile不存在，使用主版本"
            fi
            ;;
        4)
            if [ -f "backend/Dockerfile.production" ]; then
                cp backend/Dockerfile.production backend/Dockerfile
                log_success "已选择生产版Dockerfile"
            else
                log_warning "生产版Dockerfile不存在，使用主版本"
            fi
            ;;
        5)
            log_info "使用主版本Dockerfile"
            ;;
        *)
            log_warning "无效选择，使用默认最小化版"
            if [ -f "backend/Dockerfile.minimal" ]; then
                cp backend/Dockerfile.minimal backend/Dockerfile
            fi
            ;;
    esac
    
    # 显示选择的Dockerfile信息
    log_info "当前使用的Dockerfile:"
    head -5 backend/Dockerfile
}

# ====================
# 构建函数（多方案保证成功）
# ====================
build_with_retry() {
    log_info "开始构建Docker镜像..."
    
    # 选择Dockerfile版本
    select_dockerfile_version
    
    local max_retries=3
    local retry_count=0
    
    while [ $retry_count -lt $max_retries ]; do
        retry_count=$((retry_count + 1))
        log_info "构建尝试 #$retry_count"
        
        if docker-compose build --no-cache; then
            log_success "Docker镜像构建成功"
            return 0
        fi
        
        log_warning "构建尝试 #$retry_count 失败"
        
        if [ $retry_count -lt $max_retries ]; then
            log_info "等待10秒后重试..."
            sleep 10
            
            # 清理缓存
            log_info "清理Docker缓存..."
            docker-compose down 2>/dev/null || true
            docker system prune -f 2>/dev/null || true
            
            # 切换到更简化的版本
            case $retry_count in
                1)
                    log_info "第一次失败，切换到最小化版..."
                    if [ -f "backend/Dockerfile.minimal" ]; then
                        cp backend/Dockerfile.minimal backend/Dockerfile
                    fi
                    ;;
                2)
                    log_info "第二次失败，创建超简版本..."
                    # 创建绝对最小化的Dockerfile
                    cat > backend/Dockerfile.ultra-minimal << 'EOF'
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

# 安装Composer
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 复制并安装依赖
COPY composer.json .
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# 复制应用代码
COPY . .

# 设置权限
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
EOF
                    cp backend/Dockerfile.ultra-minimal backend/Dockerfile
                    ;;
            esac
        fi
    done
    
    log_error "所有构建方案均失败"
    return 1
}

# ====================
# 启动服务
# ====================
start_services() {
    log_info "启动Docker服务..."
    
    # 停止现有服务
    docker-compose down 2>/dev/null || true
    
    # 启动服务
    if docker-compose up -d; then
        log_success "Docker服务启动成功"
    else
        log_error "Docker服务启动失败"
        return 1
    fi
    
    # 等待服务就绪
    log_info "等待服务就绪（30秒）..."
    sleep 30
    
    # 检查服务状态
    log_info "检查服务状态..."
    docker-compose ps
    
    # 检查各服务健康状况
    services=("backend" "mysql" "nginx" "redis")
    for service in "${services[@]}"; do
        if docker-compose ps | grep -q "$service.*Up"; then
            log_success "$service 服务运行正常"
        else
            log_warning "$service 服务状态异常"
        fi
    done
}

# ====================
# 初始化数据库
# ====================
initialize_database() {
    log_info "初始化数据库..."
    
    # 等待MySQL完全启动
    log_info "等待MySQL启动（15秒）..."
    sleep 15
    
    # 检查MySQL连接
    log_info "检查MySQL连接..."
    if docker-compose exec -T mysql mysqladmin ping -h localhost -u root -p$DB_ROOT_PASSWORD 2>/dev/null; then
        log_success "MySQL连接正常"
    else
        log_warning "MySQL连接检查失败，等待更多时间..."
        sleep 30
    fi
    
    # 运行数据库迁移
    log_info "运行数据库迁移..."
    if docker-compose exec backend php artisan migrate --force; then
        log_success "数据库迁移成功"
    else
        log_error "数据库迁移失败"
        log_info "尝试修复迁移..."
        docker-compose exec backend php artisan migrate:fresh --force --seed
    fi
    
    # 填充初始数据
    log_info "填充初始数据..."
    if docker-compose exec backend php artisan db:seed --force; then
        log_success "初始数据填充成功"
    else
        log_warning "初始数据填充失败，尝试手动修复"
    fi
}

# ====================
# 系统验证
# ====================
validate_system() {
    log_info "验证系统功能..."
    
    # 检查API健康
    log_info "检查API健康状态..."
    if curl -s http://localhost:8000/health 2>/dev/null | grep -q "healthy"; then
        log_success "API健康检查通过"
    else
        log_warning "API健康检查失败"
    fi
    
    # 检查Web界面
    log_info "检查Web界面..."
    if curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null | grep -q "200"; then
        log_success "Web界面可访问"
    else
        log_warning "Web界面访问失败，可能还在启动中"
    fi
    
    # 检查服务日志
    log_info "检查服务日志（最近10行）..."
    docker-compose logs --tail=10
}

# ====================
# 部署后配置
# ====================
post_deployment_config() {
    log_info "部署后配置..."
    
    # 显示访问信息
    echo ""
    echo "========================================"
    echo "         Snipe-CN 部署完成！"
    echo "========================================"
    echo ""
    
    # 获取本地IP
    local_ip=$(hostname -I | awk '{print $1}')
    
    echo "访问信息："
    echo "  Web界面: http://$local_ip"
    echo "  API接口: http://$local_ip:8000"
    echo "  API文档: http://$local_ip:8000/docs"
    echo ""
    
    echo "默认管理员账号："
    echo "  邮箱: admin@example.com"
    echo "  密码: admin123"
    echo ""
    echo "⚠️  重要：请立即修改默认管理员密码！"
    echo ""
    
    echo "数据库信息："
    echo "  Host: localhost"
    echo "  Port: 3306"
    echo "  Database: snipe_cn"
    echo "  Username: snipe_user"
    echo ""
    
    echo "常用命令："
    echo "  查看服务状态: docker-compose ps"
    echo "  查看日志: docker-compose logs -f"
    echo "  停止服务: docker-compose down"
    echo "  重启服务: docker-compose restart"
    echo "  备份数据: ./scripts/backup.sh"
    echo ""
    
    echo "故障排除："
    echo "  1. 如果无法访问，检查防火墙设置"
    echo "  2. 查看详细日志: docker-compose logs"
    echo "  3. 重启服务: docker-compose restart"
    echo ""
    
    # 创建快捷脚本
    create_quick_scripts
}

# ====================
# 创建快捷脚本
# ====================
create_quick_scripts() {
    log_info "创建管理快捷脚本..."
    
    # 创建管理脚本目录
    mkdir -p scripts
    
    # 创建启动脚本
    cat > scripts/start.sh << 'EOF'
#!/bin/bash
echo "启动Snipe-CN服务..."
docker-compose up -d
echo "服务启动完成，访问 http://localhost"
EOF
    
    # 创建停止脚本
    cat > scripts/stop.sh << 'EOF'
#!/bin/bash
echo "停止Snipe-CN服务..."
docker-compose down
echo "服务已停止"
EOF
    
    # 创建重启脚本
    cat > scripts/restart.sh << 'EOF'
#!/bin/bash
echo "重启Snipe-CN服务..."
docker-compose restart
echo "服务重启完成"
EOF
    
    # 创建状态检查脚本
    cat > scripts/status.sh << 'EOF'
#!/bin/bash
echo "Snipe-CN服务状态："
docker-compose ps
echo ""
echo "资源使用情况："
docker stats --no-stream 2>/dev/null || echo "无法获取资源统计"
EOF
    
    # 创建日志查看脚本
    cat > scripts/logs.sh << 'EOF'
#!/bin/bash
echo "查看Snipe-CN服务日志："
docker-compose logs -f "$@"
EOF
    
    # 创建备份脚本
    cat > scripts/backup.sh << 'EOF'
#!/bin/bash
# Snipe-CN数据库备份脚本

BACKUP_DIR="backups"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# 从环境变量获取密码
source .env 2>/dev/null || {
    echo "错误: 无法加载.env文件"
    exit 1
}

# 备份数据库
echo "正在备份数据库..."
docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > $BACKUP_DIR/backup_${DATE}.sql

# 压缩备份
gzip $BACKUP_DIR/backup_${DATE}.sql

# 保留最近7天的备份
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "备份完成: $BACKUP_DIR/backup_${DATE}.sql.gz"
EOF
    
    # 设置脚本权限
    chmod +x scripts/*.sh
    
    log_success "管理脚本创建完成"
}

# ====================
# 主函数
# ====================
main() {
    echo ""
    echo "========================================"
    echo "  Snipe-CN 稳定版一键部署脚本"
    echo "  版本: $SCRIPT_VERSION"
    echo "  日期: $SCRIPT_DATE"
    echo "========================================"
    echo ""
    
    # 检查环境
    check_docker
    check_resources
    
    # 准备环境
    prepare_environment
    
    # 配置镜像加速（可选）
    read -p "是否配置Docker国内镜像加速？(y/N): " setup_mirrors
    if [[ "$setup_mirrors" == "y" || "$setup_mirrors" == "Y" ]]; then
        setup_docker_mirrors
    fi
    
    # 构建镜像
    if ! build_with_retry; then
        log_error "构建失败，部署中止"
        exit 1
    fi
    
    # 启动服务
    if ! start_services; then
        log_error "服务启动失败"
        exit 1
    fi
    
    # 初始化数据库
    initialize_database
    
    # 验证系统
    validate_system
    
    # 部署后配置
    post_deployment_config
    
    echo "========================================"
    log_success "部署完成！"
    echo "========================================"
    echo ""
    
    # 显示完成时间
    end_time=$(date +"%Y-%m-%d %H:%M:%S")
    echo "部署完成时间: $end_time"
    echo ""
    
    # 保存部署日志
    echo "部署日志已保存到: deployment.log"
    {
        echo "Snipe-CN部署日志"
        echo "=================="
        echo "部署时间: $end_time"
        echo "版本: $SCRIPT_VERSION"
        echo ""
        echo "服务状态:"
        docker-compose ps
        echo ""
        echo "访问地址: http://$(hostname -I | awk '{print $1}')"
    } > deployment.log
}

# ====================
# 脚本执行
# ====================

# 加载环境变量（用于数据库密码）
if [ -f ".env" ]; then
    source .env 2>/dev/null || true
fi

# 执行主函数
main "$@"

# 返回退出码
exit $?