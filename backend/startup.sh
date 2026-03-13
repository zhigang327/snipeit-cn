#!/bin/bash

# Snipe-CN 应用启动脚本
# 在容器启动时执行必要的初始化任务
# 版本: 1.0.0
# 日期: 2026-03-12

set -e

echo "========================================"
echo "  Snipe-CN 应用启动初始化"
echo "========================================"
echo ""

# 切换到应用目录
cd /var/www/html || { echo "错误: 无法切换到应用目录"; exit 1; }

# 1. 检查artisan文件
if [ ! -f "artisan" ]; then
    echo "错误: artisan文件不存在"
    echo "请确保应用代码正确部署"
    exit 1
fi

echo "✓ artisan文件存在"

# 2. 检查.env文件
if [ ! -f ".env" ]; then
    echo "创建.env文件..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✓ 从.env.example创建.env文件"
    else
        echo "警告: .env.example文件不存在，创建默认.env文件"
        cat > .env << 'EOF'
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
EOF
        echo "✓ 创建默认.env文件"
    fi
else
    echo "✓ .env文件已存在"
fi

# 3. 生成应用密钥（如果不存在）
if ! grep -q "^APP_KEY=" .env; then
    echo "生成应用密钥..."
    
    # 尝试使用artisan生成密钥
    if php artisan key:generate --force 2>/dev/null; then
        echo "✓ 使用artisan生成应用密钥"
    else
        echo "警告: artisan生成密钥失败，使用PHP生成"
        # 使用PHP生成安全的随机密钥
        php -r "\$key = 'base64:' . base64_encode(random_bytes(32)); file_put_contents('.env', preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . \$key, file_get_contents('.env')));"
        echo "✓ 使用PHP生成应用密钥"
    fi
else
    echo "✓ 应用密钥已存在"
fi

# 4. 设置目录权限
echo "设置目录权限..."
mkdir -p \
    storage \
    storage/framework \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data \
    storage \
    bootstrap/cache

chmod -R 775 \
    storage \
    bootstrap/cache

echo "✓ 目录权限设置完成"

# 5. 优化应用
echo "优化应用..."
if composer dump-autoload --optimize 2>/dev/null; then
    echo "✓ 优化自动加载器"
else
    echo "⚠ 自动加载器优化跳过"
fi

if php artisan optimize 2>/dev/null; then
    echo "✓ 优化应用"
else
    echo "⚠ 应用优化跳过"
fi

# 6. 检查数据库连接
echo "检查数据库连接..."
max_attempts=30
attempt=1

while [ $attempt -le $max_attempts ]; do
    if php artisan db:check 2>/dev/null || php -r "
        try {
            new PDO('mysql:host=db;port=3306', 'snipeit', 'password');
            exit(0);
        } catch (PDOException \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "✓ 数据库连接成功 (尝试 $attempt/$max_attempts)"
        break
    fi
    
    if [ $attempt -eq $max_attempts ]; then
        echo "警告: 数据库连接失败，应用可能无法正常工作"
        echo "      请检查数据库服务是否正常运行"
        break
    fi
    
    echo "等待数据库... (尝试 $attempt/$max_attempts)"
    sleep 2
    attempt=$((attempt + 1))
done

# 7. 运行数据库迁移（如果配置了数据库）
if grep -q "^DB_CONNECTION=mysql" .env; then
    echo "运行数据库迁移..."
    if php artisan migrate --force 2>/dev/null; then
        echo "✓ 数据库迁移完成"
    else
        echo "⚠ 数据库迁移失败，将在后续重试"
    fi
fi

# 8. 清理缓存
echo "清理缓存..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo "✓ 缓存清理完成"

echo ""
echo "========================================"
echo "  应用启动初始化完成"
echo "========================================"
echo ""

# 9. 启动PHP-FPM
echo "启动PHP-FPM服务..."
exec php-fpm "$@"