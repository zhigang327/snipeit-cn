#!/bin/bash
# 修复Docker构建上下文问题脚本
# 解决COPY ../.env.example路径错误

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
echo "  修复 Docker 构建上下文问题"
echo "========================================"
echo ""
echo "问题：Docker构建时出现错误"
echo "  COPY ../.env.example .env.example 2>/dev/null || true"
echo "  failed to solve: failed to compute cache key"
echo ""
echo "原因：Docker构建上下文是backend目录，但.env.example在项目根目录"
echo "解决方案：复制.env.example到backend目录或修改docker-compose.yml"
echo ""

# 检查当前目录
if [ ! -f "docker-compose.yml" ]; then
    log_error "请确保在项目根目录运行此脚本"
    exit 1
fi

# 检查.env.example文件
if [ -f ".env.example" ]; then
    log_success "找到.env.example文件（在根目录）"
elif [ -f "backend/.env.example" ]; then
    log_success "找到.env.example文件（在backend目录）"
else
    log_warning "未找到.env.example文件，将创建默认版本"
    # 创建.env.example文件
    cat > .env.example << 'EOF'
APP_NAME=Snipe-CN
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=snipeit
DB_USERNAME=snipeit
DB_PASSWORD=password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=admin@example.com
MAIL_FROM_NAME="${APP_NAME}"
EOF
    log_success "已创建.env.example文件"
fi

# 提供解决方案选项
echo ""
echo "========================================"
echo "  解决方案选项"
echo "========================================"
echo ""
echo "方案1: 将.env.example复制到backend目录（推荐）"
echo "方案2: 修改docker-compose.yml构建上下文"
echo "方案3: 修改Dockerfile使用绝对路径"
echo ""
echo "请选择解决方案:"
echo "1. 方案1 - 复制.env.example到backend目录"
echo "2. 方案2 - 修改docker-compose.yml构建上下文"
echo "3. 方案3 - 修改Dockerfile使用相对路径"
echo ""

read -p "请选择 (1-3，默认1): " choice
choice=${choice:-1}

case $choice in
    1)
        # 方案1: 复制.env.example到backend目录
        log_info "采用方案1: 复制.env.example到backend目录"
        
        if [ -f ".env.example" ]; then
            cp .env.example backend/.env.example
            log_success "已将.env.example复制到backend目录"
        elif [ -f "backend/.env.example" ]; then
            log_success ".env.example已在backend目录"
        else
            log_error "未找到.env.example文件"
            exit 1
        fi
        
        # 修改所有Dockerfile，移除../前缀
        for dockerfile in backend/Dockerfile backend/Dockerfile.offline backend/Dockerfile.minimal backend/Dockerfile.stable backend/Dockerfile.production backend/Dockerfile.smart backend/Dockerfile.simple; do
            if [ -f "$dockerfile" ]; then
                log_info "修改 $dockerfile..."
                sed -i.bak 's/COPY \.\.\/\.env\.example/COPY \.env\.example/g' "$dockerfile"
                log_success "$dockerfile 已修改"
            fi
        done
        
        # 创建备份文件以便恢复
        cp docker-compose.yml docker-compose.yml.backup
        
        log_success "方案1实施完成"
        ;;
        
    2)
        # 方案2: 修改docker-compose.yml构建上下文
        log_info "采用方案2: 修改docker-compose.yml构建上下文"
        
        # 备份原始文件
        cp docker-compose.yml docker-compose.yml.backup
        
        # 修改构建上下文从backend目录改为根目录
        sed -i.bak 's/context: \.\/backend/context: \./g' docker-compose.yml
        
        # 同样需要复制.env.example到backend目录（因为Dockerfile现在从根目录构建）
        if [ -f ".env.example" ]; then
            cp .env.example backend/.env.example
            log_success "已将.env.example复制到backend目录"
        fi
        
        # 修改Dockerfile中的路径
        for dockerfile in backend/Dockerfile backend/Dockerfile.offline backend/Dockerfile.minimal backend/Dockerfile.stable backend/Dockerfile.production backend/Dockerfile.smart backend/Dockerfile.simple; do
            if [ -f "$dockerfile" ]; then
                log_info "修改 $dockerfile..."
                sed -i.bak 's/COPY \.\.\/\.env\.example/COPY backend\/\.env\.example/g' "$dockerfile"
                log_success "$dockerfile 已修改"
            fi
        done
        
        log_success "方案2实施完成"
        ;;
        
    3)
        # 方案3: 修改Dockerfile使用相对路径（使用当前构建上下文）
        log_info "采用方案3: 修改Dockerfile使用相对路径"
        
        # 修改所有Dockerfile
        for dockerfile in backend/Dockerfile backend/Dockerfile.offline backend/Dockerfile.minimal backend/Dockerfile.stable backend/Dockerfile.production backend/Dockerfile.smart backend/Dockerfile.simple; do
            if [ -f "$dockerfile" ]; then
                log_info "修改 $dockerfile..."
                sed -i.bak 's/COPY \.\.\/\.env\.example \.env\.example 2>\/dev\/null \|\| true//g' "$dockerfile"
                
                # 添加新的复制逻辑
                cat > /tmp/dockerfile_patch << 'EOF'
# 复制.env.example文件（如果存在）
RUN if [ -f ../.env.example ]; then \
        cp ../.env.example .env.example; \
        echo "✓ 从上级目录复制.env.example"; \
    elif [ -f .env.example ]; then \
        echo "✓ .env.example已存在"; \
    else \
        echo "⚠ 未找到.env.example文件，创建默认版本"; \
        cat > .env.example << 'ENVFILE'
APP_NAME=Snipe-CN
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
ENVFILE; \
        echo "✓ 已创建默认.env.example"; \
    fi
EOF
                
                # 插入新的逻辑到Dockerfile
                # 找到COPY composer.json的行，在其后面插入
                linenum=$(grep -n "COPY composer.json" "$dockerfile" | head -1 | cut -d: -f1)
                if [ -n "$linenum" ]; then
                    # 备份原始文件
                    cp "$dockerfile" "$dockerfile.backup"
                    
                    # 插入新的内容
                    head -n "$linenum" "$dockerfile" > /tmp/dockerfile_head
                    tail -n +"$(($linenum+1))" "$dockerfile" > /tmp/dockerfile_tail
                    
                    cat /tmp/dockerfile_head /tmp/dockerfile_patch /tmp/dockerfile_tail > "$dockerfile"
                    
                    log_success "$dockerfile 已修改"
                else
                    log_warning "无法在 $dockerfile 中找到合适的位置插入"
                fi
            fi
        done
        
        log_success "方案3实施完成"
        ;;
        
    *)
        log_error "无效选择"
        exit 1
        ;;
esac

# 验证修复
echo ""
echo "========================================"
echo "  验证修复"
echo "========================================"
echo ""

# 检查所有Dockerfile
for dockerfile in backend/Dockerfile backend/Dockerfile.offline backend/Dockerfile.minimal backend/Dockerfile.stable backend/Dockerfile.production backend/Dockerfile.smart backend/Dockerfile.simple; do
    if [ -f "$dockerfile" ]; then
        if grep -q "COPY \.\.\/\.env\.example" "$dockerfile"; then
            log_error "$dockerfile 中仍有 ../.env.example 引用"
        else
            log_success "$dockerfile 已修复"
        fi
    fi
done

# 检查.env.example文件位置
if [ -f "backend/.env.example" ]; then
    log_success "backend/.env.example 文件存在"
elif [ -f ".env.example" ]; then
    log_warning ".env.example 在根目录但不在backend目录"
else
    log_error "未找到.env.example文件"
fi

# 检查docker-compose.yml
if grep -q "context: ./backend" docker-compose.yml; then
    log_info "docker-compose.yml 构建上下文为backend目录"
else
    log_info "docker-compose.yml 构建上下文已修改"
fi

# 提供测试建议
echo ""
echo "========================================"
echo "  测试建议"
echo "========================================"
echo ""
echo "1. 测试Docker构建:"
echo "   docker-compose build backend --no-cache"
echo ""
echo "2. 如果仍然失败，请尝试:"
echo "   docker-compose down"
echo "   docker system prune -f"
echo "   docker-compose build backend --no-cache --progress=plain"
echo ""
echo "3. 查看详细错误:"
echo "   docker-compose build backend --no-cache --progress=plain 2>&1 | grep -A 5 -B 5 \"env.example\""
echo ""
echo "4. 使用备份文件恢复:"
echo "   cp docker-compose.yml.backup docker-compose.yml"
echo "   cp backend/Dockerfile.backup backend/Dockerfile"
echo ""

log_success "修复完成！请尝试重新构建Docker镜像。"