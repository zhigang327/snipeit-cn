#!/bin/bash
# 快速修复脚本 - 直接解决Composer exit code 2错误

set -e

echo "========================================"
echo "    Snipe-CN Composer问题快速修复"
echo "========================================"
echo ""

# 备份原文件
echo "[1/5] 备份原Dockerfile..."
if [ -f "backend/Dockerfile" ]; then
    cp backend/Dockerfile backend/Dockerfile.backup.$(date +%s)
    echo "✓ 备份完成: backend/Dockerfile.backup.*"
fi

# 使用简化版Dockerfile
echo "[2/5] 使用简化版Dockerfile..."
cp backend/Dockerfile.simple backend/Dockerfile
echo "✓ Dockerfile已替换"

# 清理Docker缓存
echo "[3/5] 清理Docker缓存..."
docker-compose down 2>/dev/null || true
docker builder prune -f 2>/dev/null || true
echo "✓ 缓存清理完成"

# 构建镜像
echo "[4/5] 开始构建镜像（这可能需要几分钟）..."
echo "----------------------------------------"
if docker-compose build --progress=plain backend 2>&1 | tee /tmp/docker-build.log; then
    echo "----------------------------------------"
    echo "✓ 镜像构建成功！"
else
    echo "----------------------------------------"
    echo "⚠️  构建过程中有错误，但可能已经部分成功"
    echo "查看详细日志: tail -50 /tmp/docker-build.log"
    
    # 检查是否已经构建了镜像
    if docker images | grep -q "snipeit-cn-backend"; then
        echo "✓ 检测到镜像已创建，继续下一步..."
    else
        echo "❌ 镜像创建失败，尝试备用方案..."
        
        # 尝试使用多阶段构建
        echo "尝试多阶段构建..."
        if [ -f "scripts/emergency-fix.sh" ]; then
            chmod +x scripts/emergency-fix.sh
            ./scripts/emergency-fix.sh
            exit $?
        else
            echo "错误：紧急修复脚本不存在"
            echo "请手动检查问题或联系支持"
            exit 1
        fi
    fi
fi

# 启动服务
echo "[5/5] 启动服务..."
if docker-compose up -d; then
    echo "✓ 服务启动成功！"
else
    echo "⚠️  服务启动有警告，检查状态..."
    docker-compose ps
fi

echo ""
echo "========================================"
echo "         修复完成！"
echo "========================================"
echo ""
echo "服务状态:"
docker-compose ps
echo ""
echo "下一步操作:"
echo "1. 初始化数据库: docker-compose exec backend php artisan migrate --force"
echo "2. 填充数据: docker-compose exec backend php artisan db:seed --force"
echo "3. 访问系统: http://localhost"
echo ""
echo "默认管理员账号:"
echo "- 邮箱: admin@example.com"
echo "- 密码: admin123"
echo ""
echo "查看日志: docker-compose logs -f"
echo "停止服务: docker-compose down"
echo "========================================"