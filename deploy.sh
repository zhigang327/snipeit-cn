#!/bin/bash
# Snipe-CN 一键部署脚本 v2.0
# 无交互，自动处理所有环境问题
set -e

# ──────────────────────────────────────────
# 颜色
# ──────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC}   $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

# ──────────────────────────────────────────
# 1. 确认在项目根目录
# ──────────────────────────────────────────
[ -f docker-compose.yml ] || error "请在项目根目录运行此脚本"

# ──────────────────────────────────────────
# 2. 安装 / 修复 Docker & docker-compose
# ──────────────────────────────────────────
info "检查 Docker 环境..."

# 安装 Docker（如未安装）
if ! command -v docker &>/dev/null; then
    warn "Docker 未安装，正在安装..."
    curl -fsSL https://get.docker.com | sh
    sudo systemctl enable --now docker
    sudo usermod -aG docker "$USER" 2>/dev/null || true
fi

# 检测损坏的 docker-compose（HTML 文件）
if command -v docker-compose &>/dev/null; then
    if head -c 9 "$(command -v docker-compose)" 2>/dev/null | grep -q '<!DOCTYPE'; then
        warn "检测到损坏的 docker-compose，正在删除..."
        sudo rm -f "$(command -v docker-compose)"
    fi
fi

# 确保 docker compose plugin 可用，并建立 docker-compose 包装器
if docker compose version &>/dev/null 2>&1; then
    success "docker compose plugin 可用"
elif command -v docker-compose &>/dev/null; then
    success "独立版 docker-compose 可用"
else
    warn "安装 docker-compose-plugin..."
    sudo apt-get update -qq && sudo apt-get install -y -qq docker-compose-plugin
fi

# 建立兼容包装脚本（统一用 docker-compose 调用）
if ! command -v docker-compose &>/dev/null; then
    sudo tee /usr/local/bin/docker-compose >/dev/null <<'WRAP'
#!/bin/sh
exec docker compose "$@"
WRAP
    sudo chmod +x /usr/local/bin/docker-compose
    success "已创建 docker-compose 包装脚本"
fi

success "Docker $(docker --version | grep -oP '[\d.]+' | head -1)"
success "Compose $(docker-compose version 2>&1 | grep -oP '[\d.]+' | head -1)"

# ──────────────────────────────────────────
# 3. 准备 .env
# ──────────────────────────────────────────
info "准备 .env 配置..."

if [ ! -f .env ]; then
    if [ -f backend/.env.example ]; then
        cp backend/.env.example .env
        success "已从 backend/.env.example 创建 .env"
    else
        error "找不到 .env.example，无法继续"
    fi
else
    success ".env 已存在"
fi

# 检查必填项是否有值（没有则设置默认值）
set_default() {
    local key="$1" val="$2"
    if ! grep -q "^${key}=.\+" .env 2>/dev/null; then
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${val}|" .env
        else
            echo "${key}=${val}" >> .env
        fi
        warn "${key} 未设置，使用默认值: ${val}"
    fi
}

set_default DB_ROOT_PASSWORD  "RootPass_$(openssl rand -hex 4)"
set_default DB_DATABASE        "snipe_cn"
set_default DB_USERNAME        "snipe_user"
set_default DB_PASSWORD        "SnipePass_$(openssl rand -hex 4)"
set_default DB_PORT            "3306"
set_default REDIS_PORT         "6379"
set_default BACKEND_PORT       "8000"
set_default FRONTEND_PORT      "5173"
set_default NGINX_PORT         "80"
set_default APP_URL            "http://localhost"
set_default VITE_API_URL       "/api"

success ".env 配置完成"

# ──────────────────────────────────────────
# 4. 构建镜像
# ──────────────────────────────────────────
info "构建 Docker 镜像（首次构建约需 5-10 分钟）..."
docker-compose build --no-cache
success "镜像构建完成"

# ──────────────────────────────────────────
# 5. 启动服务
# ──────────────────────────────────────────
info "停止旧服务..."
docker-compose down --remove-orphans 2>/dev/null || true

info "启动所有服务..."
docker-compose up -d
success "服务已启动"

# ──────────────────────────────────────────
# 6. 等待 MySQL 健康
# ──────────────────────────────────────────
info "等待 MySQL 就绪（最长 120 秒）..."
MAX=60; i=1
while [ $i -le $MAX ]; do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' snipe-cn-mysql 2>/dev/null || echo "none")
    if [ "$STATUS" = "healthy" ]; then
        success "MySQL 就绪"
        break
    fi
    echo -ne "\r  等待中... ($i/$MAX) 状态: $STATUS    "
    sleep 2; i=$((i+1))
done
echo ""
if [ $i -gt $MAX ]; then warn "MySQL 健康检查超时，继续尝试"; fi

# ──────────────────────────────────────────
# 7. 等待 backend 容器稳定
# ──────────────────────────────────────────
info "等待 backend 初始化（最长 120 秒）..."
MAX=60; i=1
while [ $i -le $MAX ]; do
    STATUS=$(docker inspect --format='{{.State.Status}}' snipe-cn-backend 2>/dev/null || echo "none")
    if [ "$STATUS" = "running" ]; then
        # 再检查没在重启
        RESTARTING=$(docker inspect --format='{{.State.Restarting}}' snipe-cn-backend 2>/dev/null || echo "true")
        if [ "$RESTARTING" = "false" ]; then
            success "backend 运行正常"
            break
        fi
    fi
    echo -ne "\r  等待中... ($i/$MAX) 状态: $STATUS    "
    sleep 2; i=$((i+1))
done
echo ""

# ──────────────────────────────────────────
# 8. 显示最终状态
# ──────────────────────────────────────────
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  容器状态"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
docker-compose ps

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
SUCCESS=true
for svc in snipe-cn-mysql snipe-cn-redis snipe-cn-backend snipe-cn-frontend snipe-cn-nginx; do
    STATUS=$(docker inspect --format='{{.State.Status}}' "$svc" 2>/dev/null || echo "not found")
    RESTARTING=$(docker inspect --format='{{.State.Restarting}}' "$svc" 2>/dev/null || echo "false")
    if [ "$STATUS" = "running" ] && [ "$RESTARTING" = "false" ]; then
        echo -e "  ${GREEN}✓${NC} $svc"
    else
        echo -e "  ${RED}✗${NC} $svc  [$STATUS / restarting=$RESTARTING]"
        SUCCESS=false
    fi
done
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ──────────────────────────────────────────
# 9. 输出访问信息
# ──────────────────────────────────────────
NGINX_PORT_VAL=$(grep "^NGINX_PORT=" .env | cut -d= -f2 | tr -d '"' || echo "80")
LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")

echo ""
if [ "$SUCCESS" = "true" ]; then
    echo -e "${GREEN}====== 部署成功！======${NC}"
    echo ""
    echo "  🌐 访问地址:  http://${LOCAL_IP}:${NGINX_PORT_VAL}"
    echo "  📋 查看日志:  docker-compose logs -f"
    echo "  🔄 重启服务:  docker-compose restart"
    echo "  🛑 停止服务:  docker-compose down"
    echo ""
else
    echo -e "${YELLOW}====== 部署完成，但部分服务异常 ======${NC}"
    echo ""
    echo "  排查命令:"
    echo "    docker-compose logs backend   # 查看后端日志"
    echo "    docker-compose logs frontend  # 查看前端日志"
    echo "    docker-compose logs nginx     # 查看 nginx 日志"
    echo ""
fi
