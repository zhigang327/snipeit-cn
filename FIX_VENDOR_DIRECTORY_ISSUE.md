# 修复 "没有vendor目录" 错误指南

## 问题描述

在构建 Docker 镜像时出现以下错误：

```
#25 0.249 ⚠ 没有vendor目录，需要手动安装依赖
#25 0.249 请先运行: composer install --no-dev --optimize-autoloader
```

这个错误发生在使用 `Dockerfile.ultra-simple` 时，该版本假设 vendor 目录已经存在。

## 问题根源

1. **构建过程**：
   - `deploy-stable.sh` 脚本在构建失败两次后会自动创建并使用 `Dockerfile.ultra-simple`
   - 该版本假设 `vendor/` 目录已经存在于项目中
   - 如果 `vendor/` 目录不存在，构建就会失败

2. **vendor目录是什么**：
   - 这是 PHP Composer 依赖管理工具生成的目录
   - 包含所有第三方依赖库
   - 通常通过运行 `composer install` 生成

## 解决方案

### 方案1：使用修复脚本（推荐）

运行专门的修复脚本：

```bash
./fix-ultra-simple-issue.sh
```

这个脚本会自动：
- 检测是否有 vendor 目录
- 创建改进的 `Dockerfile.ultra-simple`
- 提供多种安装模式选择

### 方案2：手动安装依赖

如果网络正常，可以在项目根目录运行：

```bash
# 进入backend目录
cd backend

# 安装PHP依赖
composer install --no-dev --optimize-autoloader

# 返回项目根目录
cd ..
```

### 方案3：切换其他Dockerfile版本

修改 `deploy-stable.sh` 选择不同的版本：

```bash
# 编辑部署脚本，在select_dockerfile_version函数中
# 或者手动复制：

# 使用最小化版
cp backend/Dockerfile.minimal backend/Dockerfile

# 使用离线版（推荐）
cp backend/Dockerfile.offline backend/Dockerfile

# 使用稳定版
cp backend/Dockerfile.stable backend/Dockerfile
```

### 方案4：使用离线包

如果网络有问题：

```bash
# 创建离线vendor包
./fix-network-issues.sh --offline

# 然后重新构建
docker-compose build --no-cache
```

## 改进的Dockerfile.ultra-simple特性

新的版本支持三种模式：

### 模式1：离线模式
- 如果 `vendor/` 目录存在，直接复制使用
- 适合已经安装好依赖的环境

### 模式2：网络安装模式
- 如果没有 `vendor/` 目录，尝试网络安装
- 使用阿里云镜像，3600秒超时
- 内置重试机制

### 模式3：最小化结构模式
- 如果网络安装失败，创建最小化结构
- 应用可以启动，但部分PHP功能受限
- 提供基础环境，后续可以完善

## 详细步骤

### 步骤1：分析当前状态

```bash
# 检查是否有vendor目录
ls -la backend/vendor/

# 检查当前使用的Dockerfile
head -5 backend/Dockerfile
```

### 步骤2：选择解决方案

根据网络情况选择：

1. **网络正常**：使用方案2（手动安装）或方案1（修复脚本）
2. **网络不稳定**：使用方案4（离线包）
3. **无网络**：使用方案3（切换离线版）

### 步骤3：执行构建

```bash
# 清理缓存
docker-compose down 2>/dev/null || true
docker system prune -f

# 构建镜像
docker-compose build --no-cache --progress=plain

# 启动服务
docker-compose up -d
```

## 验证修复

构建完成后验证：

```bash
# 检查容器状态
docker-compose ps

# 检查backend容器
docker-compose exec backend ls -la vendor/

# 检查PHP依赖
docker-compose exec backend php -m | grep -E "pdo|mbstring|mysql"
```

## 预防措施

### 开发环境
```bash
# 确保vendor目录在.gitignore中
echo "vendor/" >> .gitignore

# 但composer.json和composer.lock要提交
git add composer.json composer.lock
git commit -m "Add composer files"
```

### 部署环境
```bash
# 部署前检查
if [ ! -d "vendor" ]; then
    echo "警告: 没有vendor目录"
    echo "运行: composer install --no-dev --optimize-autoloader"
fi

# 或者使用离线部署包
./scripts/create-offline-package.sh
```

## 常见问题

### Q1: composer install 太慢或失败
**解决方法**：
```bash
# 使用国内镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 增加超时时间
composer config -g process-timeout 3600

# 清理缓存后重试
composer clear-cache
composer install --no-dev --optimize-autoloader
```

### Q2: 还是构建失败怎么办？
**解决方法**：
```bash
# 使用最简单的Dockerfile
cat > backend/Dockerfile.simplest << 'EOF'
FROM php:8.2-fpm
WORKDIR /var/www/html
COPY . .
EXPOSE 9000
CMD ["php-fpm"]
EOF

cp backend/Dockerfile.simplest backend/Dockerfile
docker-compose build
```

### Q3: 如何完全避免这个问题？
**解决方法**：
```bash
# 修改deploy-stable.sh，禁用ultra-simple版本
# 注释掉相关代码，或设置更合理的重试逻辑

# 或者使用稳定的版本
sed -i 's/Dockerfile.ultra-simple/Dockerfile.offline/g' deploy-stable.sh
```

## 相关脚本

项目中包含的修复脚本：

1. **`fix-ultra-simple-issue.sh`** - 专门修复此问题
2. **`fix-network-issues.sh`** - 修复网络问题，可创建离线包
3. **`fix-composer-network.sh`** - 修复Composer网络问题
4. **`quick-fix.sh`** - 一键修复常见问题

## 总结

"没有vendor目录"错误是由于构建过程中使用的Dockerfile版本假设vendor目录已经存在。通过使用提供的修复脚本或手动切换Dockerfile版本，可以轻松解决此问题。

**推荐解决方案**：
1. 运行 `./fix-ultra-simple-issue.sh`
2. 选择适合的模式
3. 重新构建镜像

这样可以确保在各种环境下都能成功构建和部署。