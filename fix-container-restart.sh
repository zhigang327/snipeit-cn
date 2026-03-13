#!/bin/bash
# 修复容器重启问题脚本

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
echo "  修复容器重启问题"
echo "========================================"
echo ""
echo "问题：backend和frontend容器持续重启"
echo "原因：Dockerfile和docker-compose.yml的命令配置不一致"
echo "解决方案：统一配置，确保命令一致"
echo ""
echo "========================================"
echo "  诊断当前问题"
echo "========================================"
echo ""
echo "1. 检查docker-compose.yml配置..."
echo "2. 检查backend和frontend容器的启动命令..."
echo "3. 检查健康检查配置..."
echo ""
echo "========================================"
echo "  实施修复"
echo "========================================"
echo ""
echo "正在修复docker-compose.yml..."
log_info "修改backend服务命令..."
log_info "修改frontend服务使用的Dockerfile..."

# 备份原始文件
cp docker-compose.yml docker-compose.yml.backup.$(date +%Y%m%d_%H%M%S)

# 修改backend服务命令
if grep -q "command: php artisan serve --host=0.0.0.0 --port=8000" docker-compose.yml; then
    sed -i 's/command: php artisan serve --host=0.0.0.0 --port=8000/command: startup.sh/g' docker-compose.yml
    log_success "backend命令已改为startup.sh"
else
    log_warning "backend命令已正确配置"
fi

# 修改frontend服务使用的Dockerfile
if grep -q "dockerfile: Dockerfile" docker-compose.yml; then
    sed -i 's/dockerfile: Dockerfile/dockerfile: Dockerfile.stable/g' docker-compose.yml
    log_success "frontend Dockerfile已改为Dockerfile.stable"
else
    log_warning "frontend Dockerfile已正确配置"
fi

# 检查启动脚本
if [ -f "backend/startup.sh" ]; then
    log_success "backend/startup.sh脚本存在"
    
    # 确保启动脚本最后一行正确
    if tail -1 backend/startup.sh | grep -q "exec php-fpm"; then
        log_success "startup.sh结尾正确：exec php-fpm"
    else
        log_warning "startup.sh结尾可能需要调整"
        echo "建议确保startup.sh最后一行是 exec php-fpm"
    fi
else
    log_error "backend/startup.sh脚本不存在"
fi

# 检查frontend Dockerfile.stable
if [ -f "frontend/Dockerfile.stable" ]; then
    log_success "frontend/Dockerfile.stable存在"
    
    # 检查CMD命令
    if grep -q "CMD \[.*npm.*run.*dev.*\]" frontend/Dockerfile.stable; then
        log_success "frontend Dockerfile.stable的CMD正确"
    else
        log_warning "frontend Dockerfile.stable的CMD可能需要检查"
    fi
else
    log_warning "frontend/Dockerfile.stable不存在，使用默认Dockerfile"
fi

echo ""
echo "========================================"
echo "  测试修复"
echo "========================================"
echo ""
echo "修复完成后，请运行以下命令测试："
echo ""
echo "1. 停止现有服务："
echo "   docker-compose down"
echo ""
echo "2. 重新构建："
echo "   docker-compose build"
echo ""
echo "3. 启动服务："
echo "   docker-compose up -d"
echo ""
echo "4. 检查服务状态："
echo "   docker-compose ps"
echo ""
echo "5. 查看日志："
echo "   docker-compose logs backend"
echo "   docker-compose logs frontend"
echo ""
echo "========================================"
echo "  常见问题解决方案"
echo "========================================"
echo ""
echo "如果容器仍然重启，请检查："
echo "1. .env文件配置是否正确"
echo "2. 数据库连接是否正常"
echo "3. 端口是否被占用"
echo "4. 内存是否充足"
echo ""
echo "========================================"
echo "  快速修复"
echo "========================================"
echo ""
echo "如果需要快速修复，可以直接修改配置文件："
echo ""
echo "1. docker-compose.yml修改内容："
echo "   backend: 删除command行或改为startup.sh"
echo "   frontend: 使用dockerfile: Dockerfile.stable"
echo ""
echo "2. 确保.env.example存在并已复制到backend目录"
echo ""
echo "========================================"
echo "  验证修复"
echo "========================================"
echo ""
echo "验证修改后的配置："
echo ""

# 显示修改结果
if grep -q "command: startup.sh" docker-compose.yml; then
    log_success "✓ backend命令已修复"
else
    log_error "✗ backend命令仍需修复"
fi

if grep -q "dockerfile: Dockerfile.stable" docker-compose.yml; then
    log_success "✓ frontend Dockerfile已修复"
else
    log_error "✗ frontend Dockerfile仍需修复"
fi

echo ""
echo "修复脚本完成！请重新测试部署。"
echo ""
echo "========================================"
echo "  后续操作"
echo "========================================"
echo ""
echo "1. 如果修复成功，删除备份文件："
echo "   rm docker-compose.yml.backup.*"
echo ""
echo "2. 更新版本号："
echo "   sed -i 's/SCRIPT_VERSION=\"1.6.3-stable\"/SCRIPT_VERSION=\"1.6.4-stable\"/' deploy-stable.sh"
echo ""
echo "3. 记录修复："
echo "   git commit -m \"修复容器重启问题：统一backend和frontend命令配置\""
echo ""