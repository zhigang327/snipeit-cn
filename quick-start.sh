#!/bin/bash

# Snipe-CN 快速启动脚本
# 用于一键部署和启动 Snipe-CN IT资产管理系统

set -e

echo "======================================"
echo "   Snipe-CN 快速启动脚本"
echo "======================================"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查Docker
check_docker() {
    echo -e "${YELLOW}检查 Docker 环境...${NC}"
    if ! command -v docker &> /dev/null; then
        echo -e "${RED}错误: 未安装 Docker${NC}"
        echo "请访问 https://docs.docker.com/engine/install/ 安装 Docker"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        echo -e "${RED}错误: 未安装 Docker Compose${NC}"
        echo "请访问 https://docs.docker.com/compose/install/ 安装 Docker Compose"
        exit 1
    fi

    echo -e "${GREEN}✓ Docker 环境检查通过${NC}"
    echo ""
}

# 检查.env文件
check_env() {
    echo -e "${YELLOW}检查环境配置...${NC}"

    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            echo -e "${GREEN}✓ 已创建 .env 文件${NC}"
            echo -e "${YELLOW}提示: 请根据需要修改 .env 文件中的配置${NC}"
        else
            echo -e "${RED}错误: 未找到 .env.example 文件${NC}"
            exit 1
        fi
    else
        echo -e "${GREEN}✓ .env 文件已存在${NC}"
    fi
    echo ""
}

# 构建镜像
build_images() {
    echo -e "${YELLOW}构建 Docker 镜像...${NC}"
    echo "这可能需要几分钟,请耐心等待..."
    echo ""

    docker-compose build

    echo -e "${GREEN}✓ 镜像构建完成${NC}"
    echo ""
}

# 启动服务
start_services() {
    echo -e "${YELLOW}启动服务...${NC}"
    docker-compose up -d

    echo -e "${GREEN}✓ 服务启动完成${NC}"
    echo ""

    # 显示服务状态
    docker-compose ps
    echo ""
}

# 等待MySQL就绪
wait_for_mysql() {
    echo -e "${YELLOW}等待 MySQL 服务就绪...${NC}"
    for i in {1..30}; do
        if docker-compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
            echo -e "${GREEN}✓ MySQL 已就绪${NC}"
            echo ""
            return 0
        fi
        echo -n "."
        sleep 2
    done

    echo ""
    echo -e "${RED}错误: MySQL 启动超时${NC}"
    exit 1
}

# 初始化数据库
init_database() {
    echo -e "${YELLOW}初始化数据库...${NC}"

    # 运行迁移
    echo "运行数据库迁移..."
    docker-compose exec -T backend php artisan migrate --force

    # 填充数据
    echo "填充初始数据..."
    docker-compose exec -T backend php artisan db:seed --force

    echo -e "${GREEN}✓ 数据库初始化完成${NC}"
    echo ""
}

# 清理缓存
clear_cache() {
    echo -e "${YELLOW}清理缓存...${NC}"
    docker-compose exec -T backend php artisan cache:clear
    docker-compose exec -T backend php artisan config:clear
    docker-compose exec -T backend php artisan route:clear
    echo -e "${GREEN}✓ 缓存清理完成${NC}"
    echo ""
}

# 显示访问信息
show_access_info() {
    echo "======================================"
    echo -e "${GREEN}🎉 部署完成!${NC}"
    echo "======================================"
    echo ""
    echo "系统访问地址:"
    echo "  - 前端: http://localhost"
    echo "  - 后端API: http://localhost:8000"
    echo ""
    echo "默认管理员账号:"
    echo "  - 邮箱: admin@example.com"
    echo "  - 密码: admin123"
    echo ""
    echo -e "${RED}⚠️  重要提示:${NC}"
    echo "  1. 首次登录后请立即修改默认密码!"
    echo "  2. 生产环境请修改 .env 中的数据库密码!"
    echo "  3. 建议配置 HTTPS 以确保安全!"
    echo ""
    echo "常用命令:"
    echo "  - 查看日志: docker-compose logs -f"
    echo "  - 重启服务: docker-compose restart"
    echo "  - 停止服务: docker-compose down"
    echo "  - 查看状态: docker-compose ps"
    echo ""
    echo "文档:"
    echo "  - 部署文档: DEPLOYMENT.md"
    echo "  - 用户手册: USER_MANUAL.md"
    echo "  - 快速入门: QUICK_START.md"
    echo ""
}

# 主流程
main() {
    check_docker
    check_env

    read -p "是否构建 Docker 镜像? (首次部署或代码更新时需要) [y/N] " build_answer
    if [[ $build_answer =~ ^[Yy]$ ]]; then
        build_images
    fi

    start_services
    wait_for_mysql

    read -p "是否初始化数据库? (首次部署时需要) [y/N] " init_answer
    if [[ $init_answer =~ ^[Yy]$ ]]; then
        init_database
    fi

    clear_cache
    show_access_info
}

# 运行主流程
main
