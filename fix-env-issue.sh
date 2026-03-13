#!/bin/bash

# Snipe-CN .env.example 文件路径修复脚本
# 解决Docker构建时找不到.env.example文件的问题
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
# 检查文件是否存在
# ====================
check_env_example() {
    log_info "检查 .env.example 文件..."
    
    if [ -f ".env.example" ]; then
        log_success "找到 .env.example 文件 (项目根目录)"
        echo "文件路径: $(pwd)/.env.example"
        return 0
    elif [ -f "../.env.example" ]; then
        log_success "找到 .env.example 文件 (上级目录)"
        echo "文件路径: $(cd .. && pwd)/.env.example"
        return 0
    else
        log_error "未找到 .env.example 文件"
        return 1
    fi
}

# ====================
# 修复单个Dockerfile
# ====================
fix_dockerfile() {
    local dockerfile="$1"
    
    if [ ! -f "$dockerfile" ]; then
        log_warning "文件不存在: $dockerfile"
        return 1
    fi
    
    log_info "修复文件: $dockerfile"
    
    # 检查是否已经修复过
    if grep -q "COPY ../.env.example .env.example" "$dockerfile"; then
        log_success "文件已修复: $dockerfile"
        return 0
    fi
    
    # 查找并修复COPY composer.json行
    if grep -q "COPY composer.json" "$dockerfile"; then
        # 获取行号
        local line_num=$(grep -n "COPY composer.json" "$dockerfile" | head -1 | cut -d: -f1)
        
        # 备份文件
        cp "$dockerfile" "$dockerfile.backup.$(date +%Y%m%d_%H%M%S)"
        
        # 在COPY composer.json行后添加COPY .env.example
        if sed -i "${line_num}aCOPY ../.env.example .env.example 2>/dev/null || true" "$dockerfile"; then
            log_success "成功修复: $dockerfile"
        else
            log_error "修复失败: $dockerfile"
            return 1
        fi
    else
        log_warning "未找到 COPY composer.json 行，可能需要手动修复: $dockerfile"
    fi
}

# ====================
# 修复所有Dockerfile
# ====================
fix_all_dockerfiles() {
    log_info "修复所有Dockerfile..."
    
    local dockerfiles=(
        "backend/Dockerfile"
        "backend/Dockerfile.offline"
        "backend/Dockerfile.minimal"
        "backend/Dockerfile.smart"
        "backend/Dockerfile.stable"
        "backend/Dockerfile.production"
        "backend/Dockerfile.simple"
        "backend/Dockerfile.fixed"
    )
    
    local fixed_count=0
    local total_count=0
    
    for dockerfile in "${dockerfiles[@]}"; do
        total_count=$((total_count + 1))
        
        if [ -f "$dockerfile" ]; then
            if fix_dockerfile "$dockerfile"; then
                fixed_count=$((fixed_count + 1))
            fi
        else
            log_info "文件不存在，跳过: $dockerfile"
        fi
    done
    
    if [ $fixed_count -gt 0 ]; then
        log_success "成功修复 $fixed_count/$total_count 个Dockerfile"
    else
        log_warning "没有需要修复的Dockerfile"
    fi
}

# ====================
# 创建应急解决方案
# ====================
create_emergency_solution() {
    log_info "创建应急解决方案..."
    
    # 1. 确保.env.example文件在正确位置
    if [ ! -f ".env.example" ] && [ -f "../.env.example" ]; then
        log_info "复制.env.example到当前目录..."
        cp ../.env.example ./
        log_success "已复制.env.example到当前目录"
    fi
    
    # 2. 创建简化版Dockerfile
    if [ ! -f "backend/Dockerfile.emergency" ]; then
        log_info "创建应急版Dockerfile..."
        
        cat > backend/Dockerfile.emergency << 'EOF'
# Snipe-CN 应急版Dockerfile
# 解决.env.example文件路径问题
# 版本: 1.0.0-emergency

FROM php:8.2-fpm

# 基础配置
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 系统依赖安装
RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP扩展安装
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip

# 安装Composer
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 复制所有文件（包含.env.example）
COPY . .

# 检查并创建.env文件
RUN if [ ! -f .env ] && [ -f .env.example ]; then \
        cp .env.example .env; \
        echo "✓ 从.env.example创建.env文件"; \
    elif [ ! -f .env ] && [ ! -f .env.example ]; then \
        echo "⚠ 警告: .env和.env.example都不存在"; \
        echo "   应用可能无法正常运行"; \
    fi

# 生成应用密钥（如果不存在）
RUN if [ -f .env ] && ! grep -q "^APP_KEY=" .env; then \
        php artisan key:generate; \
        echo "✓ 生成应用密钥"; \
    fi

# 设置目录权限
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
EOF
        
        log_success "应急版Dockerfile已创建: backend/Dockerfile.emergency"
    fi
    
    # 3. 创建快速启动脚本
    cat > quick-start-emergency.sh << 'EOF'
#!/bin/bash
# Snipe-CN 应急快速启动脚本

set -e

echo "Snipe-CN 应急快速启动"
echo "========================="

# 检查必要的文件
if [ ! -f ".env.example" ]; then
    echo "错误: 未找到 .env.example 文件"
    echo "请确保在项目根目录运行此脚本"
    exit 1
fi

# 使用应急版Dockerfile
if [ -f "backend/Dockerfile.emergency" ]; then
    echo "使用应急版Dockerfile..."
    cp backend/Dockerfile.emergency backend/Dockerfile
else
    echo "错误: 未找到应急版Dockerfile"
    exit 1
fi

# 启动Docker
echo "清理旧的Docker容器..."
docker-compose down 2>/dev/null || true

echo "构建Docker镜像..."
if docker-compose build --no-cache; then
    echo "✓ Docker镜像构建成功"
else
    echo "✗ Docker镜像构建失败"
    echo "请检查错误信息并手动修复"
    exit 1
fi

echo "启动服务..."
docker-compose up -d

echo ""
echo "========================="
echo "应急启动完成！"
echo "访问地址: http://localhost"
echo "管理员账号: admin@example.com"
echo "管理员密码: password"
echo ""
echo "注意: 这是一个应急版本，建议后续使用稳定版重新部署"
echo "========================="
EOF
    
    chmod +x quick-start-emergency.sh
    log_success "应急启动脚本已创建: quick-start-emergency.sh"
    
    echo ""
    echo "应急解决方案已准备完成！"
    echo "使用方法:"
    echo "  1. 确保在项目根目录"
    echo "  2. 运行: ./quick-start-emergency.sh"
    echo "  3. 如果仍有问题，查看错误日志: docker-compose logs"
}

# ====================
# 验证修复
# ====================
validate_fix() {
    log_info "验证修复..."
    
    # 检查.env.example文件
    if [ -f ".env.example" ] || [ -f "../.env.example" ]; then
        log_success ".env.example文件存在"
    else
        log_error ".env.example文件不存在"
        return 1
    fi
    
    # 测试Docker构建
    log_info "测试Docker构建..."
    
    # 创建测试目录
    TEST_DIR="docker-test-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$TEST_DIR"
    cd "$TEST_DIR"
    
    # 创建测试Dockerfile
    cat > Dockerfile.test << 'EOF'
FROM alpine:latest

WORKDIR /test

# 测试文件复制
COPY ../.env.example .env.example 2>/dev/null || true

RUN if [ -f .env.example ]; then \
        echo "✓ 成功复制.env.example文件"; \
        echo "文件内容前10行:"; \
        head -10 .env.example; \
    else \
        echo "✗ 无法复制.env.example文件"; \
        exit 1; \
    fi
EOF
    
    # 从上级目录复制.env.example
    if [ -f "../.env.example" ]; then
        echo "测试: 从上级目录复制.env.example..."
        
        if docker build -t env-test -f Dockerfile.test ..; then
            log_success "Docker构建测试成功"
        else
            log_error "Docker构建测试失败"
            return 1
        fi
    fi
    
    # 清理
    cd ..
    rm -rf "$TEST_DIR"
    docker rmi env-test 2>/dev/null || true
    
    log_success "验证完成"
}

# ====================
# 主函数
# ====================
main() {
    echo ""
    echo "================================================"
    echo "  Snipe-CN .env.example 文件路径修复工具"
    echo "================================================"
    echo ""
    
    # 检查参数
    if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
        echo "用法: $0 [选项]"
        echo "选项:"
        echo "  --help, -h     显示帮助信息"
        echo "  --fix-all      修复所有Dockerfile"
        echo "  --emergency    创建应急解决方案"
        echo "  --validate     验证修复"
        echo "  无参数        交互式修复"
        exit 0
    fi
    
    # 检查当前目录
    if [ ! -f "composer.json" ] && [ ! -f "backend/composer.json" ]; then
        log_error "请在Snipe-CN项目目录中运行此脚本"
        exit 1
    fi
    
    # 检查.env.example文件
    check_env_example || {
        log_error "无法找到.env.example文件"
        echo "请确保项目包含.env.example文件"
        exit 1
    }
    
    # 根据参数执行
    case "$1" in
        "--fix-all")
            fix_all_dockerfiles
            ;;
        "--emergency")
            create_emergency_solution
            ;;
        "--validate")
            validate_fix
            ;;
        *)
            # 交互式菜单
            echo "请选择操作:"
            echo "1. 修复所有Dockerfile"
            echo "2. 创建应急解决方案"
            echo "3. 验证修复"
            echo "4. 全部执行"
            echo ""
            
            read -p "选择 (1-4, 默认1): " choice
            choice=${choice:-1}
            
            case $choice in
                1)
                    fix_all_dockerfiles
                    ;;
                2)
                    create_emergency_solution
                    ;;
                3)
                    validate_fix
                    ;;
                4)
                    fix_all_dockerfiles
                    create_emergency_solution
                    validate_fix
                    ;;
                *)
                    log_error "无效选择"
                    ;;
            esac
            ;;
    esac
    
    echo ""
    echo "================================================"
    echo "  修复完成！"
    echo "================================================"
}

# 执行主函数
main "$@"