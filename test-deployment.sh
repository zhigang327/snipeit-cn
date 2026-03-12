#!/bin/bash
# Snipe-CN部署测试脚本
# 用于验证部署是否成功

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

# 测试函数
test_docker_services() {
    log_info "测试Docker服务状态..."
    
    # 检查所有服务是否运行
    services=("mysql" "redis" "backend" "frontend" "nginx")
    all_running=true
    
    for service in "${services[@]}"; do
        if docker-compose ps | grep -q "$service.*Up"; then
            log_success "$service 服务运行正常"
        else
            log_error "$service 服务未运行"
            all_running=false
        fi
    done
    
    if [ "$all_running" = true ]; then
        log_success "所有Docker服务运行正常"
        return 0
    else
        log_error "部分Docker服务运行异常"
        return 1
    fi
}

test_mysql_connection() {
    log_info "测试MySQL数据库连接..."
    
    # 加载环境变量
    if [ -f ".env" ]; then
        source .env 2>/dev/null || true
    fi
    
    # 尝试连接MySQL
    if docker-compose exec -T mysql mysqladmin ping -h localhost -u root -p$DB_ROOT_PASSWORD 2>/dev/null; then
        log_success "MySQL数据库连接成功"
        
        # 检查数据库是否存在
        if docker-compose exec -T mysql mysql -u root -p$DB_ROOT_PASSWORD -e "USE $DB_DATABASE; SHOW TABLES;" 2>/dev/null | grep -q "users"; then
            log_success "数据库表结构完整"
            return 0
        else
            log_warning "数据库表结构可能不完整"
            return 1
        fi
    else
        log_error "MySQL数据库连接失败"
        return 1
    fi
}

test_backend_api() {
    log_info "测试后端API服务..."
    
    local max_retries=10
    local retry_count=0
    local api_success=false
    
    while [ $retry_count -lt $max_retries ]; do
        retry_count=$((retry_count + 1))
        log_info "API测试尝试 #$retry_count"
        
        # 测试API健康检查
        if curl -s -f http://localhost:8000/health 2>/dev/null; then
            log_success "后端API健康检查通过"
            api_success=true
            break
        else
            log_warning "API健康检查失败，等待5秒后重试..."
            sleep 5
        fi
    done
    
    if [ "$api_success" = true ]; then
        # 测试API端点
        log_info "测试API端点..."
        
        # 测试登录接口
        if curl -s -f http://localhost:8000/api/login 2>/dev/null; then
            log_success "API登录接口可访问"
        else
            log_warning "API登录接口访问失败"
        fi
        
        # 测试资产接口
        if curl -s -f http://localhost:8000/api/assets 2>/dev/null; then
            log_success "API资产接口可访问"
        else
            log_warning "API资产接口访问失败"
        fi
        
        return 0
    else
        log_error "后端API服务不可用"
        return 1
    fi
}

test_frontend_web() {
    log_info "测试前端Web界面..."
    
    local max_retries=10
    local retry_count=0
    local web_success=false
    
    while [ $retry_count -lt $max_retries ]; do
        retry_count=$((retry_count + 1))
        log_info "Web界面测试尝试 #$retry_count"
        
        # 测试Web界面
        if curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null | grep -q "200\|302"; then
            log_success "Web界面可访问 (HTTP 200/302)"
            web_success=true
            break
        else
            log_warning "Web界面访问失败，等待5秒后重试..."
            sleep 5
        fi
    done
    
    if [ "$web_success" = true ]; then
        # 检查页面内容
        if curl -s http://localhost 2>/dev/null | grep -q "Snipe-CN\|资产管理系统"; then
            log_success "Web页面内容正确"
            return 0
        else
            log_warning "Web页面内容可能不正确"
            return 1
        fi
    else
        log_error "Web界面不可访问"
        return 1
    fi
}

test_redis_cache() {
    log_info "测试Redis缓存服务..."
    
    # 测试Redis连接
    if docker-compose exec redis redis-cli ping 2>/dev/null | grep -q "PONG"; then
        log_success "Redis缓存服务正常"
        return 0
    else
        log_error "Redis缓存服务异常"
        return 1
    fi
}

test_system_resources() {
    log_info "测试系统资源..."
    
    # 检查容器资源使用
    log_info "容器资源使用情况:"
    docker stats --no-stream 2>/dev/null || log_warning "无法获取容器资源统计"
    
    # 检查磁盘空间
    log_info "磁盘空间使用情况:"
    df -h . | head -2
    
    # 检查内存使用
    log_info "内存使用情况:"
    free -m | head -2
    
    return 0
}

test_application_functionality() {
    log_info "测试应用功能..."
    
    # 测试数据库迁移状态
    log_info "检查数据库迁移状态..."
    if docker-compose exec backend php artisan migrate:status 2>/dev/null | grep -q "Yes\|Ran"; then
        log_success "数据库迁移已完成"
    else
        log_error "数据库迁移未完成"
        return 1
    fi
    
    # 测试管理员账户
    log_info "测试管理员账户..."
    if docker-compose exec backend php artisan tinker --execute="echo \App\Models\User::where('email', 'admin@example.com')->exists() ? 'Admin exists' : 'Admin not found';" 2>/dev/null | grep -q "Admin exists"; then
        log_success "管理员账户存在"
        
        # 测试密码哈希
        if docker-compose exec backend php artisan tinker --execute="echo \Illuminate\Support\Facades\Hash::check('admin123', \App\Models\User::where('email', 'admin@example.com')->first()->password) ? 'Password OK' : 'Password mismatch';" 2>/dev/null | grep -q "Password OK"; then
            log_success "管理员密码正确"
        else
            log_warning "管理员密码可能不正确"
        fi
    else
        log_error "管理员账户不存在"
        return 1
    fi
    
    # 测试资产模型
    log_info "测试资产模型..."
    if docker-compose exec backend php artisan tinker --execute="echo class_exists('\App\Models\Asset') ? 'Asset model OK' : 'Asset model missing';" 2>/dev/null | grep -q "Asset model OK"; then
        log_success "资产模型正常"
    else
        log_error "资产模型异常"
        return 1
    fi
    
    return 0
}

generate_test_report() {
    log_info "生成测试报告..."
    
    local report_file="deployment-test-report-$(date +%Y%m%d_%H%M%S).txt"
    
    {
        echo "========================================"
        echo "       Snipe-CN部署测试报告"
        echo "========================================"
        echo "测试时间: $(date)"
        echo "系统版本: $(uname -a)"
        echo ""
        echo "1. Docker服务状态"
        echo "----------------------------------------"
        docker-compose ps
        echo ""
        echo "2. 网络连接测试"
        echo "----------------------------------------"
        echo "MySQL连接: $(test_mysql_connection >/dev/null 2>&1 && echo '✓' || echo '✗')"
        echo "Redis连接: $(test_redis_cache >/dev/null 2>&1 && echo '✓' || echo '✗')"
        echo "API连接: $(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health 2>/dev/null)"
        echo "Web连接: $(curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)"
        echo ""
        echo "3. 系统资源"
        echo "----------------------------------------"
        docker stats --no-stream 2>/dev/null || echo "无法获取"
        echo ""
        echo "4. 应用功能"
        echo "----------------------------------------"
        docker-compose exec backend php artisan migrate:status 2>/dev/null | head -20
        echo ""
        echo "5. 日志摘要"
        echo "----------------------------------------"
        docker-compose logs --tail=20 2>/dev/null
        echo ""
        echo "========================================"
        echo "测试结论:"
        echo ""
        
        # 生成结论
        if test_docker_services >/dev/null 2>&1 && \
           test_mysql_connection >/dev/null 2>&1 && \
           test_backend_api >/dev/null 2>&1 && \
           test_frontend_web >/dev/null 2>&1; then
            echo "✅ 部署测试通过 - 系统运行正常"
            echo "建议: 可以开始使用系统"
        else
            echo "❌ 部署测试失败 - 存在未解决的问题"
            echo "建议: 查看详细日志，修复问题后重试"
        fi
        
        echo ""
        echo "访问信息:"
        echo "  Web界面: http://localhost"
        echo "  API接口: http://localhost:8000"
        echo "  API文档: http://localhost:8000/docs"
        echo ""
        echo "管理员账号:"
        echo "  邮箱: admin@example.com"
        echo "  密码: admin123"
        echo ""
        echo "========================================"
    } > "$report_file"
    
    log_success "测试报告已生成: $report_file"
    cat "$report_file"
}

# 主测试函数
main() {
    echo ""
    echo "========================================"
    echo "  Snipe-CN部署测试脚本"
    echo "========================================"
    echo ""
    
    # 检查是否在项目目录
    if [ ! -f "docker-compose.yml" ]; then
        log_error "未在项目根目录"
        exit 1
    fi
    
    # 运行测试
    log_info "开始部署测试..."
    echo ""
    
    # 测试1: Docker服务
    if test_docker_services; then
        log_success "✅ Docker服务测试通过"
    else
        log_error "❌ Docker服务测试失败"
    fi
    echo ""
    
    # 测试2: MySQL数据库
    if test_mysql_connection; then
        log_success "✅ MySQL数据库测试通过"
    else
        log_error "❌ MySQL数据库测试失败"
    fi
    echo ""
    
    # 测试3: Redis缓存
    if test_redis_cache; then
        log_success "✅ Redis缓存测试通过"
    else
        log_error "❌ Redis缓存测试失败"
    fi
    echo ""
    
    # 测试4: 后端API
    if test_backend_api; then
        log_success "✅ 后端API测试通过"
    else
        log_error "❌ 后端API测试失败"
    fi
    echo ""
    
    # 测试5: 前端Web
    if test_frontend_web; then
        log_success "✅ 前端Web测试通过"
    else
        log_error "❌ 前端Web测试失败"
    fi
    echo ""
    
    # 测试6: 应用功能
    if test_application_functionality; then
        log_success "✅ 应用功能测试通过"
    else
        log_error "❌ 应用功能测试失败"
    fi
    echo ""
    
    # 测试7: 系统资源
    test_system_resources
    echo ""
    
    # 生成测试报告
    generate_test_report
    
    echo ""
    echo "========================================"
    echo "测试完成!"
    echo "========================================"
    echo ""
    
    # 提供建议
    log_info "建议操作:"
    echo "1. 立即修改默认管理员密码"
    echo "2. 配置邮件通知功能"
    echo "3. 导入现有资产数据"
    echo "4. 设置定期备份任务"
    echo ""
}

# 执行主函数
main "$@"