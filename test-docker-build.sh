#!/bin/bash

# Snipe-CN Docker构建测试脚本
# 验证.env.example文件路径问题已修复
# 版本: 1.0.0
# 日期: 2026-03-12

set -e

# ====================
# 颜色定义
# ====================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ====================
# 日志函数
# ====================
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# ====================
# 测试Dockerfile
# ====================
test_dockerfile() {
    local dockerfile="$1"
    local test_name="$2"
    
    echo ""
    echo "=== 测试: $test_name ==="
    echo "文件: $dockerfile"
    
    if [ ! -f "$dockerfile" ]; then
        log_warning "文件不存在: $dockerfile"
        return 1
    fi
    
    # 检查是否包含.env.example复制
    if grep -q "COPY ../.env.example .env.example" "$dockerfile"; then
        log_success "✓ 已修复: 包含.env.example复制"
    else
        log_error "✗ 未修复: 缺少.env.example复制"
        return 1
    fi
    
    # 检查.env处理逻辑
    if grep -q "cp .env.example .env" "$dockerfile"; then
        log_success "✓ 已修复: 包含.env文件创建逻辑"
    else
        log_error "✗ 未修复: 缺少.env文件创建逻辑"
        return 1
    fi
    
    # 测试Docker构建
    log_info "测试Docker构建..."
    
    # 创建临时测试目录
    local test_dir="docker-test-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$test_dir/backend"
    
    # 复制测试文件
    cp "$dockerfile" "$test_dir/backend/Dockerfile"
    cp composer.json "$test_dir/backend/" 2>/dev/null || true
    cp backend/composer.json "$test_dir/backend/" 2>/dev/null || true
    cp .env.example "$test_dir/" 2>/dev/null || cp ../.env.example "$test_dir/" 2>/dev/null || true
    
    # 创建简化的docker-compose.yml用于测试
    cat > "$test_dir/docker-compose.yml" << 'EOF'
version: '3.8'

services:
  test:
    build:
      context: ./backend
      dockerfile: Dockerfile
    command: sh -c "echo '测试成功！' && ls -la && if [ -f .env ]; then echo '✓ .env文件存在'; else echo '✗ .env文件不存在'; exit 1; fi"
EOF
    
    cd "$test_dir"
    
    # 测试构建
    if docker-compose build --no-cache --progress=plain 2>&1 | grep -q "Successfully built"; then
        log_success "✓ Docker构建成功"
        
        # 测试运行
        if docker-compose run --rm test; then
            log_success "✓ Docker运行成功"
            test_result=0
        else
            log_error "✗ Docker运行失败"
            test_result=1
        fi
    else
        log_error "✗ Docker构建失败"
        test_result=1
    fi
    
    # 清理
    cd ..
    docker-compose down 2>/dev/null || true
    rm -rf "$test_dir"
    
    return $test_result
}

# ====================
# 测试所有Dockerfile
# ====================
test_all_dockerfiles() {
    echo ""
    echo "================================================"
    echo "  Snipe-CN Dockerfile构建测试"
    echo "================================================"
    echo ""
    
    local test_cases=(
        "backend/Dockerfile:主版本"
        "backend/Dockerfile.offline:离线版"
        "backend/Dockerfile.minimal:最小化版"
        "backend/Dockerfile.stable:稳定版"
        "backend/Dockerfile.production:生产版"
        "backend/Dockerfile.smart:智能版"
        "backend/Dockerfile.simple:简化版"
    )
    
    local passed=0
    local failed=0
    local total=0
    
    for test_case in "${test_cases[@]}"; do
        dockerfile="${test_case%:*}"
        test_name="${test_case#*:}"
        total=$((total + 1))
        
        if test_dockerfile "$dockerfile" "$test_name"; then
            passed=$((passed + 1))
        else
            failed=$((failed + 1))
        fi
        
        echo ""
    done
    
    echo "=== 测试总结 ==="
    echo "总共测试: $total 个Dockerfile"
    echo "通过: $passed"
    echo "失败: $failed"
    
    if [ $failed -eq 0 ]; then
        log_success "✓ 所有Dockerfile测试通过！"
        return 0
    else
        log_error "✗ 有 $failed 个Dockerfile测试失败"
        return 1
    fi
}

# ====================
# 快速验证修复
# ====================
quick_verify() {
    echo ""
    echo "快速验证.env.example修复..."
    echo ""
    
    # 检查文件是否存在
    if [ -f ".env.example" ] || [ -f "../.env.example" ]; then
        log_success "✓ 找到.env.example文件"
    else
        log_error "✗ 未找到.env.example文件"
        return 1
    fi
    
    # 检查主Dockerfile
    echo "检查主Dockerfile..."
    if [ -f "backend/Dockerfile" ]; then
        if grep -q "COPY ../.env.example .env.example" "backend/Dockerfile"; then
            log_success "✓ 主Dockerfile已修复"
        else
            log_error "✗ 主Dockerfile未修复"
            return 1
        fi
    fi
    
    # 测试构建
    echo "测试快速构建..."
    
    # 创建简化的测试Dockerfile
    cat > Dockerfile.test << 'EOF'
FROM alpine:latest

WORKDIR /app

# 测试文件复制
COPY .env.example .env.example 2>/dev/null || true

RUN if [ -f .env.example ]; then \
        echo "✓ 成功: .env.example文件存在"; \
        echo "文件大小: $(wc -l < .env.example) 行"; \
        exit 0; \
    else \
        echo "✗ 失败: .env.example文件不存在"; \
        exit 1; \
    fi
EOF
    
    # 确保.env.example在当前目录
    if [ ! -f ".env.example" ] && [ -f "../.env.example" ]; then
        cp ../.env.example ./
    fi
    
    if docker build -t test-env -f Dockerfile.test .; then
        log_success "✓ 快速构建测试成功"
        docker rmi test-env 2>/dev/null || true
        rm -f Dockerfile.test
        return 0
    else
        log_error "✗ 快速构建测试失败"
        rm -f Dockerfile.test
        return 1
    fi
}

# ====================
# 主函数
# ====================
main() {
    # 检查参数
    if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
        echo "用法: $0 [选项]"
        echo "选项:"
        echo "  --help, -h     显示帮助信息"
        echo "  --all          测试所有Dockerfile"
        echo "  --quick        快速验证修复"
        echo "  --verify       验证修复并显示详细信息"
        echo "  无参数        交互式测试"
        exit 0
    fi
    
    # 检查是否在正确目录
    if [ ! -f "composer.json" ] && [ ! -f "backend/composer.json" ]; then
        log_error "请在Snipe-CN项目目录中运行此脚本"
        exit 1
    fi
    
    case "$1" in
        "--all")
            test_all_dockerfiles
            ;;
        "--quick")
            quick_verify
            ;;
        "--verify")
            quick_verify && test_all_dockerfiles
            ;;
        *)
            # 交互式菜单
            echo "请选择测试模式:"
            echo "1. 快速验证修复"
            echo "2. 测试所有Dockerfile"
            echo "3. 完整验证（快速+全部）"
            echo ""
            
            read -p "选择 (1-3, 默认1): " choice
            choice=${choice:-1}
            
            case $choice in
                1)
                    quick_verify
                    ;;
                2)
                    test_all_dockerfiles
                    ;;
                3)
                    quick_verify && test_all_dockerfiles
                    ;;
                *)
                    log_error "无效选择"
                    ;;
            esac
            ;;
    esac
    
    echo ""
    echo "================================================"
    echo "  测试完成！"
    echo "================================================"
}

# 执行主函数
main "$@"