#!/bin/sh
set -e

echo "========================================="
echo "  Snipe-CN 启动初始化"
echo "========================================="

APP_DIR="/var/www/html"
cd "$APP_DIR"

# ---------- 1. 检查 artisan ----------
[ -f artisan ] || { echo "[ERROR] artisan 文件不存在，请检查镜像构建"; exit 1; }
echo "[OK] artisan 存在"

# ---------- 2. 准备 .env ----------
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "[OK] 从 .env.example 创建 .env"
    else
        echo "[ERROR] 缺少 .env.example，无法创建 .env"; exit 1
    fi
else
    echo "[OK] .env 已存在"
fi

# 注入容器环境变量到 .env（docker-compose environment 优先）
[ -n "$DB_HOST" ]     && sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST|" .env
[ -n "$DB_PORT" ]     && sed -i "s|^DB_PORT=.*|DB_PORT=$DB_PORT|" .env
[ -n "$DB_DATABASE" ] && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" .env
[ -n "$DB_USERNAME" ] && sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" .env
[ -n "$DB_PASSWORD" ] && sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" .env
[ -n "$REDIS_HOST" ]  && sed -i "s|^REDIS_HOST=.*|REDIS_HOST=$REDIS_HOST|" .env
[ -n "$REDIS_PORT" ]  && sed -i "s|^REDIS_PORT=.*|REDIS_PORT=$REDIS_PORT|" .env
[ -n "$APP_URL" ]     && sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env

# ---------- 3. 生成 APP_KEY ----------
if grep -q "^APP_KEY=$\|^APP_KEY=your\|^APP_KEY= *$" .env 2>/dev/null || ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    php artisan key:generate --force
    echo "[OK] APP_KEY 已生成"
else
    echo "[OK] APP_KEY 已存在"
fi

# ---------- 4. 目录权限 ----------
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo "[OK] 目录权限设置完成"

# ---------- 5. 等待 MySQL ----------
echo "[INFO] 等待 MySQL 就绪..."
MAX=60
i=1
while [ $i -le $MAX ]; do
    if php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}',
                [PDO::ATTR_TIMEOUT => 3]
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "[OK] MySQL 连接成功（第 $i 次）"
        break
    fi
    echo "[WAIT] MySQL 未就绪，等待中... ($i/$MAX)"
    sleep 2
    i=$((i + 1))
done

if [ $i -gt $MAX ]; then
    echo "[WARN] MySQL 连接超时，继续启动（迁移将跳过）"
fi

# ---------- 6. 数据库迁移 ----------
if php artisan migrate --force 2>&1; then
    echo "[OK] 数据库迁移完成"
else
    echo "[WARN] 数据库迁移失败，检查数据库连接和表结构"
fi

# ---------- 7. 清理缓存、优化 ----------
php artisan config:cache  2>/dev/null || true
php artisan route:cache   2>/dev/null || true
php artisan view:cache    2>/dev/null || true
echo "[OK] 缓存优化完成"

echo "========================================="
echo "  初始化完成，启动 PHP-FPM"
echo "========================================="

exec php-fpm
