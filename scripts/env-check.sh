#!/bin/bash
# 环境配置检查脚本
# 检查部署前的环境配置

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

# 检查命令是否存在
check_command() {
    if command -v $1 &> /dev/null; then
        print_success "$1 已安装"
        return 0
    else
        print_error "$1 未安装"
        return 1
    fi
}

# 检查端口是否被占用
check_port() {
    local port=$1
    local service=$2
    
    if netstat -tuln | grep -q ":$port "; then
        print_warning "端口 $port ($service) 已被占用"
        return 1
    else
        print_success "端口 $port ($service) 可用"
        return 0
    fi
}

# 检查环境变量
check_env() {
    local env_file=".env"
    
    if [ ! -f "$env_file" ]; then
        print_error ".env 文件不存在"
        print_info "请运行: cp .env.example .env"
        return 1
    fi
    
    print_success ".env 文件存在"
    
    # 加载环境变量
    source "$env_file" 2>/dev/null || true
    
    # 检查关键配置
    local errors=0
    local warnings=0
    
    echo ""
    print_info "检查关键配置项:"
    echo "----------------------------------------"
    
    # 检查数据库密码是否为默认值
    if [[ "$DB_PASSWORD" == "snipe_password" ]] || [[ -z "$DB_PASSWORD" ]]; then
        print_error "DB_PASSWORD 使用默认值或为空"
        ((errors++))
    else
        print_success "DB_PASSWORD 已修改"
    fi
    
    if [[ "$DB_ROOT_PASSWORD" == "root_password" ]] || [[ -z "$DB_ROOT_PASSWORD" ]]; then
        print_error "DB_ROOT_PASSWORD 使用默认值或为空"
        ((errors++))
    else
        print_success "DB_ROOT_PASSWORD 已修改"
    fi
    
    # 检查应用URL
    if [[ "$APP_URL" == "http://localhost" ]] || [[ -z "$APP_URL" ]]; then
        print_warning "APP_URL 使用默认值 localhost"
        ((warnings++))
    else
        print_success "APP_URL 已配置: $APP_URL"
    fi
    
    # 检查VITE_API_URL
    if [[ -z "$VITE_API_URL" ]]; then
        print_warning "VITE_API_URL 未配置"
        ((warnings++))
    else
        print_success "VITE_API_URL 已配置: $VITE_API_URL"
    fi
    
    echo "----------------------------------------"
    
    if [ $errors -gt 0 ]; then
        print_error "发现 $errors 个错误配置，必须修复！"
        return 1
    fi
    
    if [ $warnings -gt 0 ]; then
        print_warning "发现 $warnings 个警告配置，建议修复"
    fi
    
    print_success "环境变量检查完成"
    return 0
}

# 检查Docker配置
check_docker() {
    print_info "检查Docker配置..."
    
    # 检查Docker服务
    if systemctl is-active --quiet docker 2>/dev/null || service docker status | grep -q "running" 2>/dev/null; then
        print_success "Docker服务运行中"
    else
        print_error "Docker服务未运行"
        return 1
    fi
    
    # 检查Docker版本
    local docker_version=$(docker --version 2>/dev/null | awk '{print $3}' | sed 's/,//')
    if [[ -n "$docker_version" ]]; then
        print_success "Docker版本: $docker_version"
        
        # 检查版本是否满足要求
        local major=$(echo $docker_version | cut -d. -f1)
        local minor=$(echo $docker_version | cut -d. -f2 | cut -d- -f1)
        
        if [ $major -ge 20 ] && [ $minor -ge 10 ]; then
            print_success "Docker版本满足要求 (>=20.10)"
        else
            print_warning "Docker版本较低，建议升级到20.10+"
        fi
    else
        print_error "无法获取Docker版本"
        return 1
    fi
    
    # 检查Docker Compose
    if command -v docker-compose &> /dev/null; then
        local compose_version=$(docker-compose --version 2>/dev/null | awk '{print $3}' | sed 's/,//')
        print_success "Docker Compose版本: $compose_version"
    else
        print_error "Docker Compose未安装"
        return 1
    fi
    
    return 0
}

# 检查系统资源
check_resources() {
    print_info "检查系统资源..."
    
    # 检查内存
    local total_mem=$(free -g | awk '/^Mem:/{print $2}')
    if [ $total_mem -ge 4 ]; then
        print_success "内存: ${total_mem}GB (满足要求)"
    else
        print_warning "内存: ${total_mem}GB (建议4GB+)"
    fi
    
    # 检查磁盘空间
    local disk_space=$(df -h / | awk 'NR==2{print $4}')
    print_info "根目录剩余空间: $disk_space"
    
    # 检查CPU核心数
    local cpu_cores=$(nproc)
    if [ $cpu_cores -ge 2 ]; then
        print_success "CPU核心数: $cpu_cores (满足要求)"
    else
        print_warning "CPU核心数: $cpu_cores (建议2核+)"
    fi
    
    return 0
}

# 检查端口占用
check_ports() {
    print_info "检查端口占用情况..."
    
    if [ -f .env ]; then
        source .env 2>/dev/null || true
    fi
    
    local ports_to_check=(
        "${NGINX_PORT:-80}"
        "${BACKEND_PORT:-8000}"
        "${DB_PORT:-3306}"
        "${REDIS_PORT_LOCAL:-6379}"
    )
    
    local port_names=(
        "Nginx"
        "Backend API"
        "MySQL"
        "Redis"
    )
    
    local has_conflict=0
    
    for i in "${!ports_to_check[@]}"; do
        if ! check_port "${ports_to_check[$i]}" "${port_names[$i]}"; then
            has_conflict=1
        fi
    done
    
    if [ $has_conflict -eq 1 ]; then
        print_warning "发现端口冲突，请在 .env 文件中修改端口配置"
    fi
    
    return 0
}

# 检查依赖文件
check_files() {
    print_info "检查项目文件..."
    
    local required_files=(
        "docker-compose.yml"
        ".env.example"
        "backend/Dockerfile"
        "frontend/Dockerfile"
    )
    
    local missing_files=0
    
    for file in "${required_files[@]}"; do
        if [ -f "$file" ] || [ -d "$(dirname "$file")" ]; then
            print_success "$file 存在"
        else
            print_error "$file 不存在"
            ((missing_files++))
        fi
    done
    
    if [ $missing_files -gt 0 ]; then
        print_error "缺失 $missing_files 个必要文件"
        return 1
    fi
    
    return 0
}

# 生成检查报告
generate_report() {
    local timestamp=$(date +"%Y-%m-%d %H:%M:%S")
    local hostname=$(hostname)
    
    echo ""
    echo "========================================"
    echo "        Snipe-CN 环境检查报告"
    echo "========================================"
    echo "检查时间: $timestamp"
    echo "主机名: $hostname"
    echo "系统: $(uname -srm)"
    echo "========================================"
    echo ""
}

# 主函数
main() {
    echo ""
    echo "========================================"
    echo "   Snipe-CN 部署环境检查工具"
    echo "========================================"
    echo ""
    
    # 生成报告头
    generate_report
    
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    
    # 检查系统命令
    print_info "1. 检查系统命令..."
    ((total_tests++))
    if check_command "docker" && check_command "docker-compose" && check_command "git"; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 检查Docker配置
    ((total_tests++))
    if check_docker; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 检查项目文件
    ((total_tests++))
    if check_files; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 检查环境变量
    ((total_tests++))
    if check_env; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 检查系统资源
    ((total_tests++))
    if check_resources; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 检查端口占用
    ((total_tests++))
    if check_ports; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    
    # 显示总结
    echo ""
    echo "========================================"
    echo "             检查结果总结"
    echo "========================================"
    echo "总检查项: $total_tests"
    echo "通过项: $passed_tests"
    echo "失败项: $failed_tests"
    echo "========================================"
    
    if [ $failed_tests -eq 0 ]; then
        print_success "✅ 所有检查通过，可以开始部署！"
        echo ""
        echo "下一步操作:"
        echo "1. 启动服务: docker-compose up -d"
        echo "2. 初始化数据库: docker-compose exec backend php artisan migrate --force"
        echo "3. 填充数据: docker-compose exec backend php artisan db:seed --force"
        echo "4. 访问系统: http://localhost:${NGINX_PORT:-80}"
    else
        print_error "❌ 发现 $failed_tests 个问题，请先修复后再部署"
        echo ""
        echo "常见解决方案:"
        echo "1. 安装缺失的软件: docker, docker-compose, git"
        echo "2. 修改 .env 文件中的默认密码"
        echo "3. 解决端口冲突"
        echo "4. 确保有足够的系统资源"
    fi
    
    echo ""
    echo "详细部署指南请查看: DOCKER_DEPLOYMENT_GUIDE.md"
    echo "========================================"
}

# 运行主函数
main "$@"