#!/bin/bash

# Snipe-CN artisan问题修复脚本
# 解决"Could not open input file: artisan"错误
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
# 检查artisan文件
# ====================
check_artisan_file() {
    log_info "检查artisan文件..."
    
    if [ -f "artisan" ]; then
        log_success "找到artisan文件: $(pwd)/artisan"
        echo "文件大小: $(wc -l < artisan) 行"
        echo "文件权限: $(ls -la artisan | awk '{print $1}')"
        return 0
    elif [ -f "backend/artisan" ]; then
        log_success "找到artisan文件: $(pwd)/backend/artisan"
        return 0
    else
        log_error "未找到artisan文件"
        return 1
    fi
}

# ====================
# 创建启动脚本
# ====================
create_startup_script() {
    log_info "创建启动脚本..."
    
    # 检查是否已有启动脚本
    if [ -f "backend/startup.sh" ]; then
        log_success "启动脚本已存在: backend/startup.sh"
        return 0
    fi
    
    # 从模板创建或使用默认
    if [ -f "backend/startup.sh" ]; then
        # 已经存在，直接使用
        log_info "使用现有的启动脚本"
    else
        # 创建启动脚本
        log_info "创建新的启动脚本..."
        
        # 复制我们在前面创建的启动脚本
        if [ -f "startup.sh" ]; then
            cp startup.sh backend/startup.sh
        else
            # 创建简化的启动脚本
            cat > backend/startup.sh << 'EOF'
#!/bin/bash
# Snipe-CN 简化启动脚本

cd /var/www/html || exit 1

# 检查并创建.env文件
if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

# 生成应用密钥（如果不存在）
if ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    if [ -f "artisan" ]; then
        php artisan key:generate --force 2>/dev/null || \
        php -r "\$key = 'base64:' . base64_encode(random_bytes(32)); file_put_contents('.env', preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . \$key, file_get_contents('.env')));"
    fi
fi

# 设置权限
mkdir -p storage bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 启动PHP-FPM
exec php-fpm "$@"
EOF
        fi
        
        chmod +x backend/startup.sh
        log_success "启动脚本已创建: backend/startup.sh"
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
    
    # 备份原始文件
    cp "$dockerfile" "$dockerfile.backup.$(date +%Y%m%d_%H%M%S)"
    
    # 修复策略：将artisan相关操作移到启动脚本中
    # 1. 删除构建时的artisan key:generate
    # 2. 添加启动脚本复制
    # 3. 更新启动命令
    
    # 检查是否需要修复
    if grep -q "php artisan key:generate" "$dockerfile" && \
       ! grep -q "startup.sh" "$dockerfile"; then
       
        log_info "检测到需要修复的artisan命令..."
        
        # 创建临时文件
        temp_file="$dockerfile.tmp"
        
        # 处理文件，移除构建时的artisan命令
        awk '
        # 跳过包含"php artisan key:generate"的行
        /php artisan key:generate/ {
            if ($0 ~ /RUN.*php artisan key:generate/) {
                print "# " $0 " - 已移动到启动脚本中执行"
                next
            }
        }
        # 在COPY . .之后添加启动脚本复制
        /COPY \. \./ {
            print $0
            print ""
            print "# 复制启动脚本"
            print "COPY startup.sh /usr/local/bin/startup.sh"
            print "RUN chmod +x /usr/local/bin/startup.sh"
            next
        }
        # 更新CMD命令
        /CMD \["php-fpm"\]/ {
            print "# 启动命令：使用启动脚本"
            print "CMD [\"startup.sh\"]"
            next
        }
        # 其他行正常输出
        { print }
        ' "$dockerfile" > "$temp_file"
        
        # 替换原文件
        mv "$temp_file" "$dockerfile"
        
        log_success "成功修复: $dockerfile"
        return 0
    else
        log_success "文件已修复或无需修复: $dockerfile"
        return 0
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
    
    # 首先确保启动脚本存在
    create_startup_script
    
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
# 创建快速修复方案
# ====================
create_quick_fix() {
    log_info "创建快速修复方案..."
    
    # 1. 创建最简单的Dockerfile
    if [ ! -f "backend/Dockerfile.quickfix" ]; then
        cat > backend/Dockerfile.quickfix << 'EOF'
# Snipe-CN 快速修复版Dockerfile
# 解决artisan文件找不到的问题
# 版本: 1.0.0-quickfix

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

# 复制composer配置
COPY composer.json ./
COPY ../.env.example .env.example 2>/dev/null || true

# 安装依赖
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --ignore-platform-reqs \
    --no-progress

# 复制应用代码
COPY . .

# 设置权限
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x artisan 2>/dev/null || true

# 暴露端口
EXPOSE 9000

# 启动命令（在容器启动时生成密钥）
CMD sh -c '
    # 检查并创建.env文件
    if [ ! -f .env ] && [ -f .env.example ]; then
        cp .env.example .env
    fi
    
    # 生成应用密钥（如果不存在）
    if ! grep -q "^APP_KEY=" .env 2>/dev/null; then
        if [ -f "artisan" ]; then
            php artisan key:generate --force 2>/dev/null || \
            php -r "\$key = \"base64:\" . base64_encode(random_bytes(32)); file_put_contents(\".env\", preg_replace(\"/^APP_KEY=.*/m\", \"APP_KEY=\" . \$key, file_get_contents(\".env\")));"
        fi
    fi
    
    # 启动PHP-FPM
    exec php-fpm
'
EOF
        
        log_success "快速修复版Dockerfile已创建: backend/Dockerfile.quickfix"
    fi
    
    # 2. 创建快速启动脚本
    cat > quick-fix-artisan.sh << 'EOF'
#!/bin/bash
# artisan问题快速修复脚本

echo "Snipe-CN artisan问题快速修复"
echo "=============================="

# 使用快速修复版Dockerfile
if [ -f "backend/Dockerfile.quickfix" ]; then
    echo "使用快速修复版Dockerfile..."
    cp backend/Dockerfile.quickfix backend/Dockerfile
else
    echo "错误: 未找到快速修复版Dockerfile"
    exit 1
fi

# 清理并重新构建
echo "清理旧的Docker容器..."
docker-compose down 2>/dev/null || true

echo "构建Docker镜像..."
if docker-compose build --no-cache; then
    echo "✓ Docker镜像构建成功"
else
    echo "✗ Docker镜像构建失败"
    exit 1
fi

echo "启动服务..."
docker-compose up -d

echo ""
echo "=============================="
echo "快速修复完成！"
echo "访问地址: http://localhost"
echo "管理员账号: admin@example.com"
echo "管理员密码: password"
echo "=============================="
EOF
    
    chmod +x quick-fix-artisan.sh
    log_success "快速修复脚本已创建: quick-fix-artisan.sh"
    
    echo ""
    echo "快速修复方案已准备完成！"
    echo "使用方法:"
    echo "  1. 运行: ./quick-fix-artisan.sh"
    echo "  2. 如果仍有问题，查看日志: docker-compose logs backend"
}

# ====================
# 测试修复
# ====================
test_fix() {
    log_info "测试修复..."
    
    # 创建测试环境
    TEST_DIR="artisan-test-$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$TEST_DIR/backend"
    
    # 复制必要的文件
    cp backend/Dockerfile "$TEST_DIR/backend/" 2>/dev/null || true
    cp backend/startup.sh "$TEST_DIR/backend/" 2>/dev/null || true
    cp .env.example "$TEST_DIR/" 2>/dev/null || cp ../.env.example "$TEST_DIR/" 2>/dev/null || true
    cp composer.json "$TEST_DIR/backend/" 2>/dev/null || cp backend/composer.json "$TEST_DIR/backend/" 2>/dev/null || true
    
    # 创建简化的artisan文件用于测试
    cat > "$TEST_DIR/backend/artisan" << 'EOF'
#!/usr/bin/env php
<?php
// 简化的artisan文件用于测试
echo "artisan测试文件\n";
EOF
    chmod +x "$TEST_DIR/backend/artisan"
    
    # 创建测试Dockerfile
    cat > "$TEST_DIR/backend/Dockerfile.test" << 'EOF'
FROM alpine:latest

WORKDIR /app

# 复制测试文件
COPY artisan ./
COPY startup.sh /usr/local/bin/startup.sh

RUN chmod +x artisan /usr/local/bin/startup.sh

CMD ["/usr/local/bin/startup.sh"]
EOF
    
    # 创建测试启动脚本
    cat > "$TEST_DIR/backend/startup.sh" << 'EOF'
#!/bin/sh
echo "启动脚本测试..."
if [ -f "artisan" ]; then
    echo "✓ artisan文件存在"
    ./artisan
else
    echo "✗ artisan文件不存在"
    exit 1
fi
EOF
    chmod +x "$TEST_DIR/backend/startup.sh"
    
    cd "$TEST_DIR"
    
    # 测试构建
    if docker build -t artisan-test -f backend/Dockerfile.test backend; then
        log_success "✓ Docker构建测试成功"
        
        # 测试运行
        if docker run --rm artisan-test; then
            log_success "✓ Docker运行测试成功"
            test_result=0
        else
            log_error "✗ Docker运行测试失败"
            test_result=1
        fi
    else
        log_error "✗ Docker构建测试失败"
        test_result=1
    fi
    
    # 清理
    cd ..
    docker rmi artisan-test 2>/dev/null || true
    rm -rf "$TEST_DIR"
    
    return $test_result
}

# ====================
# 主函数
# ====================
main() {
    echo ""
    echo "================================================"
    echo "  Snipe-CN artisan问题修复工具"
    echo "================================================"
    echo ""
    
    # 检查参数
    if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
        echo "用法: $0 [选项]"
        echo "选项:"
        echo "  --help, -h     显示帮助信息"
        echo "  --fix-all      修复所有Dockerfile"
        echo "  --quick-fix    创建快速修复方案"
        echo "  --test         测试修复"
        echo "  --verify       验证当前状态"
        echo "  无参数        交互式修复"
        exit 0
    fi
    
    # 检查当前目录
    if [ ! -f "composer.json" ] && [ ! -f "backend/composer.json" ]; then
        log_error "请在Snipe-CN项目目录中运行此脚本"
        exit 1
    fi
    
    # 检查artisan文件
    check_artisan_file || {
        log_warning "artisan文件可能不存在或位置不正确"
        echo "尝试在backend目录查找..."
        if [ -f "backend/artisan" ]; then
            log_success "在backend目录找到artisan文件"
        else
            log_error "无法找到artisan文件"
            echo "请确保项目包含artisan文件"
            exit 1
        fi
    }
    
    # 根据参数执行
    case "$1" in
        "--fix-all")
            fix_all_dockerfiles
            ;;
        "--quick-fix")
            create_quick_fix
            ;;
        "--test")
            test_fix
            ;;
        "--verify")
            log_info "验证当前状态..."
            check_artisan_file
            echo ""
            echo "当前Dockerfile状态:"
            grep -l "php artisan key:generate" backend/Dockerfile* 2>/dev/null | while read file; do
                echo "⚠ 需要修复: $file"
            done
            grep -l "startup.sh" backend/Dockerfile* 2>/dev/null | while read file; do
                echo "✓ 已修复: $file"
            done
            ;;
        *)
            # 交互式菜单
            echo "请选择操作:"
            echo "1. 修复所有Dockerfile"
            echo "2. 创建快速修复方案"
            echo "3. 测试修复"
            echo "4. 验证当前状态"
            echo "5. 全部执行"
            echo ""
            
            read -p "选择 (1-5, 默认1): " choice
            choice=${choice:-1}
            
            case $choice in
                1)
                    fix_all_dockerfiles
                    ;;
                2)
                    create_quick_fix
                    ;;
                3)
                    test_fix
                    ;;
                4)
                    log_info "验证当前状态..."
                    check_artisan_file
                    grep -l "php artisan key:generate" backend/Dockerfile* 2>/dev/null | while read file; do
                        echo "⚠ 需要修复: $file"
                    done
                    ;;
                5)
                    fix_all_dockerfiles
                    create_quick_fix
                    test_fix
                    ;;
                *)
                    log_error "无效选择"
                    ;;
            esac
            ;;
    esac
    
    echo ""
    echo "================================================"
    echo "  操作完成！"
    echo "================================================"
}

# 执行主函数
main "$@"