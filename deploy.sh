#!/bin/bash
# Snipe-CN 快速部署脚本
# 版本: v1.0.0
# 日期: 2026-03-12

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印彩色消息
print_success() {
    echo -e "${GREEN}[✓] $1${NC}"
}

print_error() {
    echo -e "${RED}[✗] $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}[!] $1${NC}"
}

print_info() {
    echo -e "${BLUE}[i] $1${NC}"
}

# 检查命令是否存在
check_command() {
    if ! command -v $1 &> /dev/null; then
        print_error "$1 未安装，请先安装 $1"
        exit 1
    fi
}

# 检查Docker和Docker Compose
check_docker() {
    print_info "检查Docker环境..."
    
    check_command "docker"
    check_command "docker-compose"
    
    DOCKER_VERSION=$(docker --version | awk '{print $3}' | sed 's/,//')
    COMPOSE_VERSION=$(docker-compose --version | awk '{print $3}' | sed 's/,//')
    
    print_success "Docker 版本: $DOCKER_VERSION"
    print_success "Docker Compose 版本: $COMPOSE_VERSION"
}

# 显示部署菜单
show_menu() {
    echo ""
    echo "=========================================="
    echo "    Snipe-CN 资产管理系统部署向导"
    echo "=========================================="
    echo "1. 完整部署（包含数据库初始化）"
    echo "2. 仅启动服务（已配置好的环境）"
    echo "3. 停止所有服务"
    echo "4. 重启所有服务"
    echo "5. 查看服务状态"
    echo "6. 查看服务日志"
    echo "7. 备份数据库"
    echo "8. 恢复数据库"
    echo "9. 清理Docker资源"
    echo "10. 系统健康检查"
    echo "11. 退出"
    echo "=========================================="
    echo ""
    
    read -p "请选择操作 (1-11): " choice
    echo ""
}

# 完整部署
full_deployment() {
    print_info "开始完整部署Snipe-CN..."
    
    # 1. 检查环境
    check_docker
    
    # 2. 检查环境变量文件
    if [ ! -f .env ]; then
        print_warning ".env 文件不存在，从模板创建..."
        if [ -f .env.example ]; then
            cp .env.example .env
            print_success "已创建 .env 文件，请编辑配置文件"
            read -p "是否要编辑 .env 文件？(y/n): " edit_env
            if [[ $edit_env == "y" || $edit_env == "Y" ]]; then
                ${EDITOR:-vi} .env
            fi
        else
            print_error ".env.example 模板文件不存在"
            exit 1
        fi
    fi
    
    # 3. 加载环境变量
    print_info "加载环境变量..."
    if [ -f .env ]; then
        source .env
        print_success "环境变量加载完成"
    else
        print_error ".env 文件不存在"
        exit 1
    fi
    
    # 4. 构建Docker镜像
    print_info "构建Docker镜像（这可能需要几分钟）..."
    
    # 第一次尝试构建
    if docker-compose build; then
        print_success "Docker镜像构建完成"
    else
        print_warning "首次构建失败，尝试使用修复脚本..."
        
        # 运行构建修复脚本
        if [ -f "scripts/fix-build.sh" ]; then
            chmod +x scripts/fix-build.sh
            if ./scripts/fix-build.sh --step-build; then
                print_success "通过分步构建修复成功"
            else
                print_error "构建修复失败，尝试清理缓存后重新构建..."
                
                # 清理缓存后重试
                ./scripts/fix-build.sh --clean-cache
                sleep 5
                
                if docker-compose build --no-cache; then
                    print_success "清理缓存后构建成功"
                else
                    print_error "所有构建尝试都失败"
                    print_info "请检查："
                    print_info "1. 网络连接是否正常"
                    print_info "2. Docker服务是否运行"
                    print_info "3. 查看详细错误: docker-compose build --no-cache"
                    exit 1
                fi
            fi
        else
            print_error "构建修复脚本不存在"
            print_info "请手动检查错误并修复"
            exit 1
        fi
    fi
    
    # 5. 启动服务
    print_info "启动所有服务..."
    docker-compose up -d
    
    # 6. 等待服务启动
    print_info "等待服务启动..."
    sleep 30
    
    # 7. 检查服务状态
    print_info "检查服务状态..."
    docker-compose ps
    
    # 8. 初始化数据库
    print_info "初始化数据库..."
    
    # 等待MySQL完全启动
    print_info "等待MySQL服务就绪..."
    sleep 20
    
    # 运行数据库迁移
    print_info "运行数据库迁移..."
    docker-compose exec backend php artisan migrate --force
    
    if [ $? -eq 0 ]; then
        print_success "数据库迁移完成"
    else
        print_error "数据库迁移失败"
        docker-compose logs mysql
        exit 1
    fi
    
    # 填充初始数据
    print_info "填充初始数据..."
    docker-compose exec backend php artisan db:seed --force
    
    if [ $? -eq 0 ]; then
        print_success "初始数据填充完成"
    else
        print_error "初始数据填充失败"
        exit 1
    fi
    
    # 9. 清理缓存
    print_info "清理应用缓存..."
    docker-compose exec backend php artisan cache:clear
    docker-compose exec backend php artisan config:clear
    docker-compose exec backend php artisan route:clear
    docker-compose exec backend php artisan view:clear
    
    print_success "缓存清理完成"
    
    # 10. 显示部署结果
    echo ""
    echo "=========================================="
    echo "        部署完成！"
    echo "=========================================="
    echo ""
    
    # 获取IP地址
    IP_ADDRESS=$(hostname -I | awk '{print $1}')
    if [ -z "$IP_ADDRESS" ]; then
        IP_ADDRESS="127.0.0.1"
    fi
    
    echo "访问地址:"
    echo "- 本地访问: http://localhost:${NGINX_PORT:-80}"
    echo "- 网络访问: http://${IP_ADDRESS}:${NGINX_PORT:-80}"
    echo ""
    echo "默认管理员账号:"
    echo "- 邮箱: admin@example.com"
    echo "- 密码: admin123"
    echo ""
    print_warning "请立即登录并修改默认密码！"
    echo ""
    echo "服务状态:"
    docker-compose ps
    echo ""
    echo "查看日志: docker-compose logs -f"
    echo "停止服务: docker-compose down"
    echo "=========================================="
}

# 仅启动服务
start_services() {
    print_info "启动所有服务..."
    docker-compose up -d
    
    if [ $? -eq 0 ]; then
        print_success "服务启动成功"
        docker-compose ps
    else
        print_error "服务启动失败"
        exit 1
    fi
}

# 停止服务
stop_services() {
    print_info "停止所有服务..."
    docker-compose down
    
    if [ $? -eq 0 ]; then
        print_success "服务停止成功"
    else
        print_error "服务停止失败"
        exit 1
    fi
}

# 重启服务
restart_services() {
    print_info "重启所有服务..."
    docker-compose restart
    
    if [ $? -eq 0 ]; then
        print_success "服务重启成功"
        docker-compose ps
    else
        print_error "服务重启失败"
        exit 1
    fi
}

# 查看服务状态
check_status() {
    print_info "服务状态:"
    docker-compose ps
    
    echo ""
    print_info "容器资源使用情况:"
    docker stats --no-stream $(docker-compose ps -q) 2>/dev/null || echo "无法获取资源使用情况"
}

# 查看日志
view_logs() {
    echo ""
    echo "选择要查看的日志:"
    echo "1. 所有服务日志"
    echo "2. 后端服务日志"
    echo "3. 前端服务日志"
    echo "4. MySQL日志"
    echo "5. Nginx日志"
    echo "6. Redis日志"
    echo "7. 返回主菜单"
    echo ""
    
    read -p "请选择 (1-7): " log_choice
    
    case $log_choice in
        1)
            docker-compose logs -f
            ;;
        2)
            docker-compose logs -f backend
            ;;
        3)
            docker-compose logs -f frontend
            ;;
        4)
            docker-compose logs -f mysql
            ;;
        5)
            docker-compose logs -f nginx
            ;;
        6)
            docker-compose logs -f redis
            ;;
        7)
            return
            ;;
        *)
            print_error "无效选择"
            ;;
    esac
}

# 备份数据库
backup_database() {
    print_info "备份数据库..."
    
    # 创建备份目录
    BACKUP_DIR="backups"
    mkdir -p $BACKUP_DIR
    
    # 获取当前时间
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    
    # 加载环境变量
    if [ -f .env ]; then
        source .env
    else
        print_error ".env 文件不存在"
        exit 1
    fi
    
    # 备份数据库
    BACKUP_FILE="$BACKUP_DIR/backup_${TIMESTAMP}.sql"
    print_info "正在备份到: $BACKUP_FILE"
    
    docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > $BACKUP_FILE
    
    if [ $? -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
        # 压缩备份文件
        gzip $BACKUP_FILE
        print_success "数据库备份完成: ${BACKUP_FILE}.gz"
        
        # 显示备份文件信息
        echo ""
        echo "备份文件列表:"
        ls -lh $BACKUP_DIR/*.gz 2>/dev/null | tail -5
        
        # 清理旧备份（保留最近7天）
        print_info "清理7天前的旧备份..."
        find $BACKUP_DIR -name "*.gz" -mtime +7 -delete
    else
        print_error "数据库备份失败"
        rm -f $BACKUP_FILE
    fi
}

# 恢复数据库
restore_database() {
    print_info "恢复数据库..."
    
    # 查找备份文件
    BACKUP_DIR="backups"
    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "备份目录不存在: $BACKUP_DIR"
        return 1
    fi
    
    # 列出备份文件
    BACKUP_FILES=($(ls -t $BACKUP_DIR/*.gz 2>/dev/null))
    
    if [ ${#BACKUP_FILES[@]} -eq 0 ]; then
        print_error "没有找到备份文件"
        return 1
    fi
    
    echo ""
    echo "可用备份文件:"
    echo "------------------------------------------"
    for i in "${!BACKUP_FILES[@]}"; do
        echo "$((i+1)). ${BACKUP_FILES[$i]}"
    done
    echo "------------------------------------------"
    echo ""
    
    read -p "请选择要恢复的备份文件编号 (1-${#BACKUP_FILES[@]}): " file_choice
    
    if [[ ! "$file_choice" =~ ^[0-9]+$ ]] || [ "$file_choice" -lt 1 ] || [ "$file_choice" -gt ${#BACKUP_FILES[@]} ]; then
        print_error "无效选择"
        return 1
    fi
    
    BACKUP_FILE=${BACKUP_FILES[$((file_choice-1))]}
    
    # 确认恢复
    echo ""
    print_warning "警告：这将覆盖当前数据库中的所有数据！"
    read -p "确定要恢复备份 ${BACKUP_FILE} 吗？(y/n): " confirm
    
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        print_info "恢复操作已取消"
        return 0
    fi
    
    # 加载环境变量
    if [ -f .env ]; then
        source .env
    else
        print_error ".env 文件不存在"
        return 1
    fi
    
    # 停止服务
    print_info "停止服务..."
    docker-compose stop backend frontend nginx
    
    # 恢复数据库
    print_info "正在恢复数据库..."
    
    # 解压备份文件
    TEMP_FILE="/tmp/restore_$(date +%s).sql"
    gunzip -c "$BACKUP_FILE" > "$TEMP_FILE"
    
    # 恢复数据库
    docker-compose exec -T mysql mysql -u root -p${DB_ROOT_PASSWORD} snipe_cn < "$TEMP_FILE"
    
    if [ $? -eq 0 ]; then
        print_success "数据库恢复成功"
        
        # 清理临时文件
        rm -f "$TEMP_FILE"
        
        # 重启服务
        print_info "重启服务..."
        docker-compose start
        
        # 清理应用缓存
        print_info "清理应用缓存..."
        docker-compose exec backend php artisan cache:clear
        docker-compose exec backend php artisan config:clear
        
        print_success "恢复完成，服务已重启"
    else
        print_error "数据库恢复失败"
        rm -f "$TEMP_FILE"
        docker-compose start
        return 1
    fi
}

# 清理Docker资源
clean_docker() {
    echo ""
    echo "选择清理选项:"
    echo "1. 清理未使用的镜像"
    echo "2. 清理未使用的容器"
    echo "3. 清理未使用的卷"
    echo "4. 清理未使用的网络"
    echo "5. 清理所有未使用的资源"
    echo "6. 查看Docker磁盘使用"
    echo "7. 返回主菜单"
    echo ""
    
    read -p "请选择 (1-7): " clean_choice
    
    case $clean_choice in
        1)
            print_info "清理未使用的镜像..."
            docker image prune -a -f
            ;;
        2)
            print_info "清理未使用的容器..."
            docker container prune -f
            ;;
        3)
            print_info "清理未使用的卷..."
            docker volume prune -f
            ;;
        4)
            print_info "清理未使用的网络..."
            docker network prune -f
            ;;
        5)
            print_info "清理所有未使用的资源..."
            docker system prune -a -f
            ;;
        6)
            print_info "Docker磁盘使用情况:"
            docker system df
            ;;
        7)
            return
            ;;
        *)
            print_error "无效选择"
            ;;
    esac
}

# 系统健康检查
health_check() {
    print_info "执行系统健康检查..."
    echo ""
    
    # 检查Docker服务
    print_info "1. 检查Docker服务..."
    if systemctl is-active --quiet docker; then
        print_success "Docker服务运行正常"
    else
        print_error "Docker服务未运行"
    fi
    
    # 检查服务状态
    print_info "2. 检查容器状态..."
    docker-compose ps
    
    # 检查端口占用
    print_info "3. 检查端口占用..."
    if [ -f .env ]; then
        source .env
        PORTS=("${NGINX_PORT:-80}" "${BACKEND_PORT:-8000}" "${DB_PORT:-3306}" "${REDIS_PORT_LOCAL:-6379}")
        
        for port in "${PORTS[@]}"; do
            if netstat -tuln | grep -q ":$port "; then
                print_success "端口 $port 已被占用（正常）"
            else
                print_warning "端口 $port 未被占用"
            fi
        done
    fi
    
    # 检查磁盘空间
    print_info "4. 检查磁盘空间..."
    df -h / | tail -1
    
    # 检查内存使用
    print_info "5. 检查内存使用..."
    free -h | grep Mem
    
    # 检查应用健康
    print_info "6. 检查应用健康..."
    if curl -s http://localhost:${BACKEND_PORT:-8000}/health > /dev/null 2>&1; then
        print_success "后端API健康检查通过"
    else
        print_error "后端API健康检查失败"
    fi
    
    # 检查数据库连接
    print_info "7. 检查数据库连接..."
    if docker-compose exec backend php artisan db:monitor > /dev/null 2>&1; then
        print_success "数据库连接正常"
    else
        print_error "数据库连接失败"
    fi
    
    echo ""
    print_success "健康检查完成"
}

# 主函数
main() {
    # 显示欢迎信息
    echo ""
    echo "=========================================="
    echo "    Snipe-CN 资产管理系统部署工具"
    echo "    Version: v1.6.0"
    echo "    Date: 2026-03-12"
    echo "=========================================="
    echo ""
    
    # 检查当前目录
    if [ ! -f "docker-compose.yml" ]; then
        print_error "请在项目根目录运行此脚本"
        print_info "当前目录: $(pwd)"
        exit 1
    fi
    
    # 主循环
    while true; do
        show_menu
        
        case $choice in
            1)
                full_deployment
                ;;
            2)
                start_services
                ;;
            3)
                stop_services
                ;;
            4)
                restart_services
                ;;
            5)
                check_status
                ;;
            6)
                view_logs
                ;;
            7)
                backup_database
                ;;
            8)
                restore_database
                ;;
            9)
                clean_docker
                ;;
            10)
                health_check
                ;;
            11)
                print_info "退出部署工具"
                echo ""
                exit 0
                ;;
            *)
                print_error "无效选择，请重新输入"
                ;;
        esac
        
        echo ""
        read -p "按回车键继续..."
    done
}

# 运行主函数
main "$@"