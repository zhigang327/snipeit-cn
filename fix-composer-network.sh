#!/bin/bash

# Snipe-CN Composer网络问题快速修复脚本
# 专门解决Composer下载超时和网络连接问题
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
# 主修复函数
# ====================
fix_composer_network() {
    echo ""
    echo "================================================"
    echo "  Composer网络问题快速修复工具"
    echo "================================================"
    echo ""
    
    log_info "检测到Composer网络超时问题，开始修复..."
    
    # 1. 检查当前Composer配置
    log_info "检查当前Composer配置..."
    if [ -f "composer.json" ]; then
        log_success "找到composer.json文件"
    else
        log_error "未找到composer.json文件"
        return 1
    fi
    
    # 2. 设置Composer全局配置
    log_info "设置Composer全局配置..."
    composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
    composer config -g process-timeout 3600
    composer config -g github-protocols https
    composer config -g secure-http false
    composer config -g discard-changes true
    composer config -g optimize-autoloader true
    
    log_success "Composer全局配置已更新"
    
    # 3. 清理Composer缓存
    log_info "清理Composer缓存..."
    composer clear-cache
    
    # 4. 尝试多个镜像源
    log_info "尝试多个镜像源..."
    
    local mirrors=(
        "https://mirrors.aliyun.com/composer/"
        "https://packagist.phpcomposer.com"
        "https://mirrors.cloud.tencent.com/composer/"
        "https://repo.packagist.org"
    )
    
    local success=false
    
    for mirror in "${mirrors[@]}"; do
        log_info "尝试镜像源: $(echo $mirror | cut -d'/' -f3)"
        
        composer config -g repo.packagist composer "$mirror"
        
        # 测试下载
        if composer diagnose 2>&1 | grep -q "OK"; then
            log_success "镜像源可用: $mirror"
            success=true
            break
        fi
        
        log_warning "镜像源不可用: $mirror"
        sleep 2
    done
    
    if [ "$success" = false ]; then
        log_error "所有镜像源都不可用"
        
        # 5. 创建离线安装方案
        log_info "创建离线安装方案..."
        create_offline_solution
        return 0
    fi
    
    # 6. 手动下载依赖包
    log_info "手动下载依赖包（绕过Docker构建）..."
    
    # 检查是否在Docker容器中
    if [ -f /.dockerenv ]; then
        log_info "在Docker容器内，使用离线安装..."
        install_in_docker
    else
        log_info "在主机环境中，直接安装依赖..."
        install_on_host
    fi
    
    log_success "修复完成！"
}

# ====================
# 在Docker容器内安装
# ====================
install_in_docker() {
    log_info "在Docker容器内安装依赖..."
    
    # 检查是否有网络
    if curl -s --connect-timeout 10 https://mirrors.aliyun.com/composer/ &> /dev/null; then
        log_info "容器内有网络，尝试安装..."
        
        # 设置长超时和重试
        export COMPOSER_MEMORY_LIMIT=-1
        export COMPOSER_ALLOW_SUPERUSER=1
        export COMPOSER_DISABLE_NETWORK=0
        
        # 使用快速安装，不检查平台要求
        composer install \
            --no-dev \
            --optimize-autoloader \
            --no-interaction \
            --no-scripts \
            --prefer-dist \
            --ignore-platform-reqs \
            --no-progress \
            --no-suggest \
            --classmap-authoritative
    else
        log_warning "容器内无网络，需要预先准备vendor目录"
        
        if [ -d "vendor" ]; then
            log_success "已有vendor目录，跳过安装"
        else
            log_error "没有vendor目录且无网络，无法安装"
            log_info "解决方案:"
            log_info "  1. 在有网络的主机上运行 'composer install --no-dev'"
            log_info "  2. 将vendor目录复制到容器中"
            log_info "  3. 使用离线安装包"
            exit 1
        fi
    fi
}

# ====================
# 在主机上安装
# ====================
install_on_host() {
    log_info "在主机上安装依赖..."
    
    # 检查是否有composer
    if ! command -v composer &> /dev/null; then
        log_info "安装Composer..."
        
        # 下载composer.phar
        curl -sS https://getcomposer.org/installer | php
        sudo mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
        
        log_success "Composer已安装"
    fi
    
    # 检查网络
    if curl -s --connect-timeout 10 https://mirrors.aliyun.com/composer/ &> /dev/null; then
        log_info "主机有网络，下载依赖包..."
        
        # 设置环境变量
        export COMPOSER_MEMORY_LIMIT=-1
        
        # 安装依赖
        composer install \
            --no-dev \
            --optimize-autoloader \
            --no-interaction \
            --no-scripts \
            --prefer-dist \
            --ignore-platform-reqs \
            --no-progress
        
        log_success "依赖包下载完成"
        
        # 创建vendor目录的备份
        if [ -d "vendor" ]; then
            tar -czf vendor-backup-$(date +%Y%m%d_%H%M%S).tar.gz vendor
            log_success "创建vendor备份: vendor-backup-*.tar.gz"
        fi
    else
        log_error "主机无网络，需要离线安装"
        create_offline_solution
    fi
}

# ====================
# 创建离线解决方案
# ====================
create_offline_solution() {
    log_info "创建离线解决方案..."
    
    # 1. 创建离线安装指南
    cat > OFFLINE_INSTALL_GUIDE.md << 'EOF'
# Snipe-CN 离线安装指南

## 问题描述
Composer无法从网络下载依赖包，通常是以下原因：
1. 网络连接问题（防火墙、代理、DNS）
2. 网络速度太慢，超时
3. 服务器在国内，访问国外源被限制

## 解决方案

### 方案1：使用预构建的vendor目录
1. 从其他已经成功安装的Snipe-CN实例复制vendor目录
2. 将vendor目录放到项目的根目录
3. 使用离线版Dockerfile构建

### 方案2：使用Docker多阶段构建绕过网络
1. 在Dockerfile中添加以下内容，跳过Composer安装：

```dockerfile
# 跳过Composer安装
RUN mkdir -p vendor || true

# 如果有本地vendor目录，复制进去
# COPY vendor/ /var/www/html/vendor/
```

### 方案3：手动准备依赖包
1. 在有网络的环境中运行：
   ```bash
   composer install --no-dev --optimize-autoloader --ignore-platform-reqs
   ```
2. 将生成的vendor目录打包：
   ```bash
   tar -czf vendor.tar.gz vendor
   ```
3. 将vendor.tar.gz传输到目标机器并解压

### 方案4：修改Dockerfile使用本地缓存
创建一个本地缓存目录，然后在Dockerfile中使用：

```dockerfile
# 复制本地缓存
COPY packages/ /root/.cache/composer/

# 使用本地缓存安装
RUN composer install --no-dev --optimize-autoloader --prefer-dist --ignore-platform-reqs
```

## 快速修复脚本
运行以下命令尝试自动修复：
```bash
chmod +x fix-network-issues.sh
./fix-network-issues.sh --offline
```
EOF
    
    log_success "离线安装指南已创建: OFFLINE_INSTALL_GUIDE.md"
    
    # 2. 创建离线版Dockerfile（如果不存在）
    if [ ! -f "backend/Dockerfile.offline" ]; then
        log_info "创建离线版Dockerfile..."
        cp backend/Dockerfile.minimal backend/Dockerfile.offline 2>/dev/null || true
        log_success "离线版Dockerfile已创建"
    fi
    
    # 3. 创建快速启动脚本
    cat > quick-start-offline.sh << 'EOF'
#!/bin/bash
# 离线快速启动脚本

set -e

echo "Snipe-CN 离线快速启动脚本"
echo ""

# 检查vendor目录
if [ ! -d "vendor" ]; then
    echo "错误: 没有vendor目录"
    echo "请先获取vendor目录:"
    echo "1. 从其他环境复制"
    echo "2. 在有网络的环境中运行 'composer install --no-dev'"
    echo "3. 使用离线安装包"
    exit 1
fi

# 使用离线版Dockerfile
if [ -f "backend/Dockerfile.offline" ]; then
    echo "使用离线版Dockerfile..."
    cp backend/Dockerfile.offline backend/Dockerfile
fi

# 启动Docker
echo "启动Docker容器..."
docker-compose down 2>/dev/null || true
docker-compose build --no-cache
docker-compose up -d

echo ""
echo "启动完成！"
echo "访问地址: http://localhost"
echo "管理员账号: admin@example.com"
echo "管理员密码: password"
EOF
    
    chmod +x quick-start-offline.sh
    log_success "离线快速启动脚本已创建: quick-start-offline.sh"
    
    # 4. 提供具体操作指导
    echo ""
    echo "================================================"
    echo "  离线解决方案已准备完成！"
    echo "================================================"
    echo ""
    echo "请执行以下步骤:"
    echo ""
    echo "1. 获取vendor目录（三种方式任选其一）:"
    echo "   a) 在有网络的环境中运行 'composer install --no-dev'"
    echo "   b) 从其他Snipe-CN实例复制vendor目录"
    echo "   c) 使用离线安装包"
    echo ""
    echo "2. 运行离线启动脚本:"
    echo "   chmod +x quick-start-offline.sh"
    echo "   ./quick-start-offline.sh"
    echo ""
    echo "3. 如果仍有问题，查看详细指南:"
    echo "   cat OFFLINE_INSTALL_GUIDE.md"
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
        echo "  --offline      创建离线解决方案"
        echo "  无参数        自动检测并修复网络问题"
        exit 0
    fi
    
    if [ "$1" = "--offline" ]; then
        create_offline_solution
        return 0
    fi
    
    # 检查是否在正确目录
    if [ ! -f "composer.json" ] && [ ! -f "../composer.json" ]; then
        log_error "请在Snipe-CN项目目录中运行此脚本"
        exit 1
    fi
    
    fix_composer_network
}

# 执行主函数
main "$@"