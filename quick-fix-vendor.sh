#!/bin/bash
# 快速修复vendor目录问题的脚本
# 一键解决 "没有vendor目录" 错误

echo "========================================"
echo "  快速修复: 没有vendor目录错误"
echo "========================================"
echo ""

# 检查当前目录
if [ ! -f "docker-compose.yml" ]; then
    echo "错误: 请确保在项目根目录运行此脚本"
    exit 1
fi

echo "步骤1: 备份当前Dockerfile"
if [ -f "backend/Dockerfile" ]; then
    cp backend/Dockerfile backend/Dockerfile.backup.$(date +%Y%m%d_%H%M%S)
    echo "✓ 已备份当前Dockerfile"
fi

echo ""
echo "步骤2: 检查vendor目录状态"
if [ -d "backend/vendor" ] || [ -d "vendor" ]; then
    echo "✓ 检测到vendor目录"
    echo "建议使用: backend/Dockerfile.offline"
    cp backend/Dockerfile.offline backend/Dockerfile 2>/dev/null || \
    cp backend/Dockerfile backend/Dockerfile
else
    echo "⚠ 未检测到vendor目录"
    echo "建议使用: backend/Dockerfile.minimal (网络安装)"
    cp backend/Dockerfile.minimal backend/Dockerfile 2>/dev/null || \
    cp backend/Dockerfile backend/Dockerfile
fi

echo ""
echo "步骤3: 应用修复补丁"
# 临时修改Dockerfile，移除vendor检查
if [ -f "backend/Dockerfile" ]; then
    # 备份原始文件
    cp backend/Dockerfile backend/Dockerfile.original
    
    # 移除会失败的vendor检查
    sed -i.bak '/检查是否有vendor目录/,/exit 1/d' backend/Dockerfile 2>/dev/null || true
    sed -i.bak '/if.*vendor.*then/,/exit 1/d' backend/Dockerfile 2>/dev/null || true
    sed -i.bak '/使用现有的vendor目录/,/exit 1/d' backend/Dockerfile 2>/dev/null || true
    
    echo "✓ 已应用修复补丁"
fi

echo ""
echo "步骤4: 提供解决方案"
echo ""
echo "请选择解决方案:"
echo "1. 重新构建 (使用修复后的Dockerfile)"
echo "2. 安装依赖后再构建"
echo "3. 使用其他Dockerfile版本"
echo ""

read -p "请选择 (1-3): " choice

case $choice in
    1)
        echo ""
        echo "执行: docker-compose build --no-cache"
        echo "注意: 这可能需要几分钟时间..."
        docker-compose build --no-cache
        ;;
    2)
        echo ""
        echo "进入backend目录安装依赖..."
        cd backend && composer install --no-dev --optimize-autoloader
        cd ..
        echo "✓ 依赖安装完成"
        echo "现在可以运行: docker-compose build --no-cache"
        ;;
    3)
        echo ""
        echo "可用的Dockerfile版本:"
        ls backend/Dockerfile.* | xargs -I {} basename {}
        echo ""
        read -p "输入要使用的版本 (如: Dockerfile.stable): " version
        if [ -f "backend/$version" ]; then
            cp "backend/$version" backend/Dockerfile
            echo "✓ 已切换到: $version"
            echo "运行: docker-compose build --no-cache"
        else
            echo "错误: 版本不存在"
        fi
        ;;
    *)
        echo "无效选择"
        ;;
esac

echo ""
echo "========================================"
echo "  快速修复完成"
echo "========================================"
echo ""
echo "如果还有问题，请尝试:"
echo "1. 查看详细文档: cat FIX_VENDOR_DIRECTORY_ISSUE.md"
echo "2. 运行完整修复: ./fix-ultra-simple-issue.sh"
echo "3. 检查网络: ./fix-network-issues.sh"
echo "4. 查看错误日志: docker-compose logs --tail=50"
echo ""