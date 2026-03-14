#!/bin/bash
# 在服务器上运行此脚本，用 Docker 临时容器生成 composer.lock
# 用法：bash scripts/gen-composer-lock.sh

set -e

BACKEND_DIR="$(cd "$(dirname "$0")/.." && pwd)/backend"
echo "==> Backend 目录: $BACKEND_DIR"

echo "==> 启动临时 PHP 容器生成 composer.lock ..."
docker run --rm \
  -e COMPOSER_ALLOW_SUPERUSER=1 \
  -v "$BACKEND_DIR:/app" \
  -w /app \
  composer:2.6.6 \
  sh -c "
    # 尝试腾讯云镜像
    composer config -g repo.packagist composer https://mirrors.cloud.tencent.com/composer/ && \
    composer update --no-install --no-scripts --no-interaction --ignore-platform-reqs 2>&1 | tail -5 && \
    echo '✓ 腾讯云镜像成功' && exit 0

    # 降级阿里云
    echo '腾讯云失败，尝试阿里云...' && \
    composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ && \
    composer update --no-install --no-scripts --no-interaction --ignore-platform-reqs 2>&1 | tail -5 && \
    echo '✓ 阿里云镜像成功' && exit 0

    # 降级官方源
    echo '阿里云失败，尝试官方源...' && \
    composer config -g --unset repos.packagist && \
    composer update --no-install --no-scripts --no-interaction --ignore-platform-reqs 2>&1 | tail -5 && \
    echo '✓ 官方源成功'
  "

echo ""
echo "==> composer.lock 生成完毕，文件大小: $(wc -c < "$BACKEND_DIR/composer.lock") bytes"
echo "==> 现在可以提交 lock 文件："
echo "    cd $(dirname "$BACKEND_DIR") && git add backend/composer.lock && git commit -m 'chore: 提交 composer.lock' && git push"
