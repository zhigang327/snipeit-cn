#!/bin/bash

# Snipe-CN网络问题诊断和修复脚本
# 解决Composer下载超时、网络连接等问题
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
# 打印标题
# ====================
print_title() {
    echo ""
    echo "================================================"
    echo "  Snipe-CN 网络问题诊断和修复工具"
    echo "================================================"
    echo ""
}

# ====================
# 检查系统要求
# ====================
check_requirements() {
    log_info "检查系统要求..."
    
    # 检查Docker
    if command -v docker &> /dev/null; then
        log_success "Docker 已安装: $(docker --version | head -1)"
    else
        log_error "Docker 未安装"
        return 1
    fi
    
    # 检查Docker Compose
    if command -v docker-compose &> /dev/null; then
        log_success "Docker Compose 已安装: $(docker-compose --version)"
    else
        log_warning "Docker Compose 未安装，尝试使用docker compose插件..."
        if docker compose version &> /dev/null; then
            log_success "Docker Compose插件可用"
        else
            log_error "Docker Compose不可用"
            return 1
        fi
    fi
    
    # 检查网络连接
    log_info "检查网络连接..."
    if ping -c 1 -W 2 mirrors.aliyun.com &> /dev/null; then
        log_success "可以访问阿里云镜像"
    else
        log_warning "无法访问阿里云镜像，可能是DNS问题"
    fi
    
    if ping -c 1 -W 2 repo.packagist.org &> /dev/null; then
        log_success "可以访问Packagist官方源"
    else
        log_warning "无法访问Packagist官方源"
    fi
}

# ====================
# 诊断网络问题
# ====================
diagnose_network() {
    log_info "开始网络诊断..."
    
    echo ""
    echo "=== 网络诊断报告 ==="
    echo ""
    
    # 1. 检查DNS解析
    log_info "检查DNS解析..."
    for domain in mirrors.aliyun.com repo.packagist.org github.com; do
        if nslookup $domain &> /dev/null; then
            echo "  ✓ $domain: DNS解析正常"
        else
            echo "  ✗ $domain: DNS解析失败"
        fi
    done
    
    # 2. 检查HTTP连接
    log_info "检查HTTP连接..."
    for url in "https://mirrors.aliyun.com/composer/" "https://repo.packagist.org/packages.json"; do
        if curl -s --connect-timeout 5 -I $url &> /dev/null; then
            echo "  ✓ $url: HTTP连接正常"
        else
            echo "  ✗ $url: HTTP连接失败"
        fi
    done
    
    # 3. 检查代理设置
    log_info "检查代理设置..."
    if [ -n "$http_proxy" ] || [ -n "$HTTP_PROXY" ]; then
        echo "  ⚠ 检测到HTTP代理设置: $http_proxy$HTTP_PROXY"
        echo "    这可能影响Docker内部网络，尝试设置Docker代理..."
    else
        echo "  ✓ 无HTTP代理设置"
    fi
    
    # 4. 检查防火墙
    log_info "检查防火墙..."
    if command -v ufw &> /dev/null; then
        ufw_status=$(sudo ufw status | grep -i active)
        if [[ $ufw_status == *"active"* ]]; then
            echo "  ⚠ UFW防火墙已启用，可能阻塞端口"
        fi
    fi
    
    # 5. 检查Docker网络
    log_info "检查Docker网络..."
    docker_network=$(docker network ls | grep snipeit-cn_default || echo "")
    if [ -n "$docker_network" ]; then
        echo "  ✓ Docker网络存在: snipeit-cn_default"
    else
        echo "  ⚠ Docker网络不存在，将自动创建"
    fi
    
    echo ""
    echo "=== 诊断完成 ==="
}

# ====================
# 修复网络问题
# ====================
fix_network_issues() {
    log_info "开始修复网络问题..."
    
    local solution=""
    read -p "选择修复方案 (1=DNS修复, 2=代理设置, 3=Docker配置, 4=全部, 默认4): " solution
    solution=${solution:-4}
    
    case $solution in
        1)
            fix_dns_problems
            ;;
        2)
            fix_proxy_settings
            ;;
        3)
            fix_docker_config
            ;;
        4)
            fix_dns_problems
            fix_proxy_settings
            fix_docker_config
            ;;
        *)
            log_error "无效选择"
            ;;
    esac
}

# ====================
# 修复DNS问题
# ====================
fix_dns_problems() {
    log_info "修复DNS问题..."
    
    # 1. 更新DNS服务器
    if [ -f /etc/resolv.conf ]; then
        log_info "备份当前DNS配置..."
        sudo cp /etc/resolv.conf /etc/resolv.conf.backup.$(date +%Y%m%d%H%M%S)
        
        log_info "添加备用DNS服务器..."
        echo "# Snipe-CN添加的DNS服务器" | sudo tee -a /etc/resolv.conf > /dev/null
        echo "nameserver 8.8.8.8" | sudo tee -a /etc/resolv.conf > /dev/null
        echo "nameserver 8.8.4.4" | sudo tee -a /etc/resolv.conf > /dev/null
        echo "nameserver 114.114.114.114" | sudo tee -a /etc/resolv.conf > /dev/null
    fi
    
    # 2. 更新Docker DNS配置
    log_info "更新Docker DNS配置..."
    if [ -f /etc/docker/daemon.json ]; then
        sudo cp /etc/docker/daemon.json /etc/docker/daemon.json.backup.$(date +%Y%m%d%H%M%S)
    fi
    
    cat << EOF | sudo tee /etc/docker/daemon.json > /dev/null
{
    "dns": ["8.8.8.8", "8.8.4.4", "114.114.114.114"],
    "dns-opts": ["timeout:2", "attempts:3"],
    "registry-mirrors": [
        "https://docker.mirrors.ustc.edu.cn",
        "https://hub-mirror.c.163.com"
    ]
}
EOF
    
    # 3. 重启Docker服务
    log_info "重启Docker服务..."
    sudo systemctl restart docker || sudo service docker restart
    
    log_success "DNS配置已更新"
}

# ====================
# 修复代理设置
# ====================
fix_proxy_settings() {
    log_info "修复代理设置..."
    
    # 1. 创建Docker代理配置文件
    log_info "配置Docker代理..."
    sudo mkdir -p /etc/systemd/system/docker.service.d/
    
    cat << EOF | sudo tee /etc/systemd/system/docker.service.d/proxy.conf > /dev/null
[Service]
Environment="HTTP_PROXY="
Environment="HTTPS_PROXY="
Environment="NO_PROXY=localhost,127.0.0.1,::1"
EOF
    
    # 2. 重新加载systemd配置
    sudo systemctl daemon-reload
    sudo systemctl restart docker
    
    # 3. 清除环境变量中的代理设置（仅当前会话）
    unset http_proxy
    unset HTTP_PROXY
    unset https_proxy
    unset HTTPS_PROXY
    
    log_success "代理设置已更新"
}

# ====================
# 修复Docker配置
# ====================
fix_docker_config() {
    log_info "修复Docker配置..."
    
    # 1. 增加Docker守护进程超时时间
    log_info "增加Docker超时时间..."
    if [ -f /etc/docker/daemon.json ]; then
        # 先备份
        sudo cp /etc/docker/daemon.json /etc/docker/daemon.json.backup.$(date +%Y%m%d%H%M%S)
        
        # 读取现有配置并更新
        if ! grep -q "\"max-concurrent-downloads\"" /etc/docker/daemon.json; then
            sudo sed -i 's/{/{\n    "max-concurrent-downloads": 3,/' /etc/docker/daemon.json
        fi
    else
        # 创建新配置
        cat << EOF | sudo tee /etc/docker/daemon.json > /dev/null
{
    "max-concurrent-downloads": 3,
    "max-concurrent-uploads": 3,
    "dns": ["8.8.8.8", "8.8.4.4"],
    "dns-opts": ["timeout:5", "attempts:3"],
    "registry-mirrors": [
        "https://docker.mirrors.ustc.edu.cn",
        "https://hub-mirror.c.163.com"
    ]
}
EOF
    fi
    
    # 2. 重启Docker
    log_info "重启Docker服务..."
    sudo systemctl restart docker || sudo service docker restart
    
    # 3. 清理Docker缓存和网络
    log_info "清理Docker资源..."
    docker system prune -f 2>/dev/null || true
    docker network prune -f 2>/dev/null || true
    
    log_success "Docker配置已更新"
}

# ====================
# 创建离线安装包
# ====================
create_offline_package() {
    log_info "创建离线安装包..."
    
    if [ ! -f "composer.json" ]; then
        log_error "未找到composer.json文件"
        return 1
    fi
    
    # 创建离线包目录
    OFFLINE_DIR="offline-packages-$(date +%Y%m%d)"
    mkdir -p "$OFFLINE_DIR"
    
    log_info "下载所有依赖包到: $OFFLINE_DIR"
    
    # 使用Composer下载所有包到本地
    if composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs \
        --no-progress \
        --working-dir="$OFFLINE_DIR"; then
        log_success "依赖包下载完成"
    else
        log_warning "Composer下载失败，尝试备用方案..."
        
        # 备用方案：使用离线的vendor目录
        if [ -d "vendor" ]; then
            log_info "复制现有的vendor目录..."
            cp -r vendor "$OFFLINE_DIR/"
            log_success "使用现有vendor目录创建离线包"
        else
            log_error "无法创建离线包，请检查网络连接"
            return 1
        fi
    fi
    
    # 创建安装脚本
    cat > "$OFFLINE_DIR/install-offline.sh" << 'EOF'
#!/bin/bash
# Snipe-CN离线安装脚本

set -e

echo "开始离线安装..."

# 检查参数
if [ $# -eq 0 ]; then
    echo "使用方法: $0 /path/to/snipeit-cn"
    exit 1
fi

TARGET_DIR="$1"

if [ ! -d "$TARGET_DIR" ]; then
    echo "目标目录不存在: $TARGET_DIR"
    exit 1
fi

# 复制vendor目录
if [ -d "vendor" ]; then
    echo "复制vendor目录..."
    rm -rf "$TARGET_DIR/vendor"
    cp -r vendor "$TARGET_DIR/"
    echo "✓ vendor目录复制完成"
else
    echo "⚠ vendor目录不存在，跳过"
fi

# 使用离线版Dockerfile
if [ -f "Dockerfile.offline" ]; then
    echo "复制离线版Dockerfile..."
    cp Dockerfile.offline "$TARGET_DIR/backend/Dockerfile"
    echo "✓ Dockerfile更新完成"
fi

echo "离线安装包已应用到: $TARGET_DIR"
echo ""
echo "下一步操作:"
echo "1. 进入目标目录: cd $TARGET_DIR"
echo "2. 运行部署脚本: ./deploy-stable.sh"
echo "3. 如果仍有问题，运行网络修复脚本: ./fix-network-issues.sh"
EOF
    
    chmod +x "$OFFLINE_DIR/install-offline.sh"
    
    # 复制Dockerfile.offline
    if [ -f "backend/Dockerfile.offline" ]; then
        cp backend/Dockerfile.offline "$OFFLINE_DIR/"
    fi
    
    # 创建压缩包
    tar -czf "$OFFLINE_DIR.tar.gz" "$OFFLINE_DIR"
    
    log_success "离线安装包创建完成: $OFFLINE_DIR.tar.gz"
    log_info "使用方法:"
    log_info "  1. 复制到目标机器: scp $OFFLINE_DIR.tar.gz user@target:/path/"
    log_info "  2. 解压: tar -xzf $OFFLINE_DIR.tar.gz"
    log_info "  3. 运行安装: cd $OFFLINE_DIR && ./install-offline.sh /path/to/snipeit-cn"
}

# ====================
# 主函数
# ====================
main() {
    print_title
    
    # 检查参数
    if [ "$1" = "--offline" ]; then
        create_offline_package
        return 0
    fi
    
    # 检查要求
    check_requirements || {
        log_error "系统要求检查失败"
        exit 1
    }
    
    # 显示菜单
    echo "请选择操作:"
    echo "1. 诊断网络问题"
    echo "2. 修复网络问题"
    echo "3. 创建离线安装包"
    echo "4. 使用离线版Dockerfile构建"
    echo ""
    
    read -p "选择 (1-4, 默认1): " choice
    choice=${choice:-1}
    
    case $choice in
        1)
            diagnose_network
            ;;
        2)
            fix_network_issues
            ;;
        3)
            create_offline_package
            ;;
        4)
            log_info "切换到离线版Dockerfile..."
            if [ -f "backend/Dockerfile.offline" ]; then
                cp backend/Dockerfile.offline backend/Dockerfile
                log_success "已切换到离线版Dockerfile"
                echo ""
                echo "现在可以尝试重新构建:"
                echo "  docker-compose down"
                echo "  docker-compose build --no-cache"
                echo "  docker-compose up -d"
            else
                log_error "离线版Dockerfile不存在"
            fi
            ;;
        *)
            log_error "无效选择"
            ;;
    esac
    
    echo ""
    echo "================================================"
    echo "  操作完成！"
    echo "================================================"
}

# 执行主函数
main "$@"