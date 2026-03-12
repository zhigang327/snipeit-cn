#!/bin/bash

# 简化版启动脚本
# 用于快速启动 Snipe-CN 项目

set -e

echo "=========================================="
echo "       Snipe-CN 项目启动脚本"
echo "=========================================="
echo ""

# 颜色定义
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# 检查Docker
echo -e "${YELLOW}检查 Docker 环境...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}错误: 未安装 Docker${NC}"
    echo "请先安装 Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    # 尝试使用 docker compose（v2）
    if docker compose version &> /dev/null; then
        echo -e "${GREEN}使用 Docker Compose v2${NC}"
    else
        echo -e "${RED}错误: 未安装 Docker Compose${NC}"
        echo "请安装 Docker Compose: https://docs.docker.com/compose/install/"
        exit 1
    fi
fi

echo -e "${GREEN}✓ Docker 环境就绪${NC}"
echo ""

# 检查环境文件
echo -e "${YELLOW}检查环境配置...${NC}"
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo -e "${YELLOW}创建 .env 文件...${NC}"
        cp .env.example .env
        echo -e "${GREEN}✓ 已创建 .env 文件${NC}"
        echo -e "${YELLOW}提示: 如需自定义配置，请编辑 .env 文件${NC}"
    else
        echo -e "${RED}错误: 未找到 .env.example 文件${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ .env 文件已存在${NC}"
fi
echo ""

# 停止现有服务
echo -e "${YELLOW}停止现有服务...${NC}"
docker-compose down 2>/dev/null || true
echo -e "${GREEN}✓ 清理完成${NC}"
echo ""

# 构建服务
echo -e "${YELLOW}构建 Docker 镜像...${NC}"
echo "这可能需要一些时间，请耐心等待..."
echo ""
docker-compose build
echo -e "${GREEN}✓ 镜像构建完成${NC}"
echo ""

# 启动服务
echo -e "${YELLOW}启动服务...${NC}"
docker-compose up -d
echo -e "${GREEN}✓ 服务启动完成${NC}"
echo ""

# 等待服务就绪
echo -e "${YELLOW}等待服务就绪...${NC}"
echo -n "等待 MySQL "
for i in {1..30}; do
    if docker-compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo ""
        echo -e "${GREEN}✓ MySQL 已就绪${NC}"
        break
    fi
    echo -n "."
    sleep 2
    
    if [ $i -eq 30 ]; then
        echo ""
        echo -e "${RED}错误: MySQL 启动超时${NC}"
        echo "请检查 MySQL 日志: docker-compose logs mysql"
        exit 1
    fi
done

echo ""
echo -e "${YELLOW}等待后端服务...${NC}"
echo -n "等待后端服务启动 "
for i in {1..20}; do
    if curl -s http://localhost:8000 > /dev/null 2>&1; then
        echo ""
        echo -e "${GREEN}✓ 后端服务已就绪${NC}"
        break
    fi
    echo -n "."
    sleep 3
    
    if [ $i -eq 20 ]; then
        echo ""
        echo -e "${YELLOW}警告: 后端服务启动较慢，继续等待...${NC}"
    fi
done

# 初始化数据库
echo ""
echo -e "${YELLOW}初始化数据库...${NC}"
docker-compose exec -T backend php artisan migrate --force
echo -e "${GREEN}✓ 数据库迁移完成${NC}"
echo ""

# 可选：填充测试数据
echo -e "${YELLOW}是否填充测试数据？${NC}"
read -p "输入 y 填充测试数据，其他键跳过: " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}填充测试数据...${NC}"
    docker-compose exec -T backend php artisan db:seed --force
    echo -e "${GREEN}✓ 测试数据填充完成${NC}"
fi
echo ""

# 清理缓存
echo -e "${YELLOW}清理缓存...${NC}"
docker-compose exec -T backend php artisan cache:clear
docker-compose exec -T backend php artisan config:clear
docker-compose exec -T backend php artisan route:clear
echo -e "${GREEN}✓ 缓存清理完成${NC}"
echo ""

# 显示状态
echo -e "${YELLOW}服务状态:${NC}"
docker-compose ps
echo ""

# 显示访问信息
echo "=========================================="
echo -e "${GREEN}🎉 Snipe-CN 启动完成！${NC}"
echo "=========================================="
echo ""
echo "访问地址:"
echo -e "  ${GREEN}前端界面:${NC} http://localhost"
echo -e "  ${GREEN}后端API:${NC} http://localhost:8000"
echo ""
echo "默认管理员账号:"
echo -e "  ${YELLOW}邮箱:${NC} admin@example.com"
echo -e "  ${YELLOW}密码:${NC} admin123"
echo ""
echo "重要提示:"
echo -e "  ${RED}1. 首次登录后请立即修改默认密码！${NC}"
echo -e "  ${RED}2. 生产环境请修改 .env 中的安全配置！${NC}"
echo ""
echo "常用命令:"
echo "  # 查看日志"
echo "  docker-compose logs -f"
echo ""
echo "  # 查看特定服务日志"
echo "  docker-compose logs -f backend"
echo "  docker-compose logs -f mysql"
echo ""
echo "  # 重启服务"
echo "  docker-compose restart"
echo ""
echo "  # 停止服务"
echo "  docker-compose down"
echo ""
echo "  # 进入容器"
echo "  docker-compose exec backend bash"
echo ""
echo "  # 重新构建"
echo "  docker-compose down && docker-compose build && docker-compose up -d"
echo ""
echo "文档:"
echo "  - 详细修复指南: FIX_11133_ERROR.md"
echo "  - 部署文档: DEPLOYMENT.md"
echo "  - 用户手册: USER_MANUAL.md"
echo ""

# 自动打开浏览器（可选）
read -p "是否在浏览器中打开前端界面？[y/N] " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if command -v open &> /dev/null; then
        open http://localhost
    elif command -v xdg-open &> /dev/null; then
        xdg-open http://localhost
    else
        echo "请手动访问: http://localhost"
    fi
fi

echo ""
echo "如有问题，请查看日志: docker-compose logs -f"
echo "或参考修复指南: cat FIX_11133_ERROR.md"