# 11133 错误修复指南

## 问题分析

根据检查，11133 错误的主要原因是：

### 1. **缺少 Laravel Vendor 依赖**
   - 项目是 Docker 化应用，但本地缺少 `vendor` 目录
   - `backend/vendor` 目录不存在，导致 PHP 类无法自动加载

### 2. **项目结构不完整**
   - 缺少标准 Laravel 目录结构（storage, bootstrap, public 等）
   - 这些文件应在 Docker 构建过程中生成

### 3. **未通过 Docker 运行**
   - 项目设计为通过 Docker Compose 运行
   - 缺少必要的容器化环境

## 解决方案

### 方案一：使用 Docker 运行（推荐）

#### 步骤 1: 准备环境
```bash
# 复制环境配置文件
cp .env.example .env

# 根据需要修改 .env 配置
# 特别关注以下配置：
# DB_* - 数据库配置
# APP_URL - 应用地址
# VITE_API_URL - 前端API地址
```

#### 步骤 2: 构建并启动服务
```bash
# 构建 Docker 镜像（首次运行或代码更新时需要）
docker-compose build

# 启动所有服务
docker-compose up -d
```

#### 步骤 3: 初始化数据库
```bash
# 等待 MySQL 服务就绪
sleep 30

# 运行数据库迁移
docker-compose exec backend php artisan migrate --force

# 可选：填充初始数据
docker-compose exec backend php artisan db:seed --force
```

#### 步骤 4: 验证服务
```bash
# 检查服务状态
docker-compose ps

# 查看日志
docker-compose logs -f backend
```

### 方案二：使用快速启动脚本

```bash
# 赋予执行权限
chmod +x quick-start.sh

# 运行快速启动脚本
./quick-start.sh
```

脚本将自动执行以下操作：
1. 检查 Docker 环境
2. 创建 `.env` 文件
3. 构建 Docker 镜像
4. 启动所有服务
5. 初始化数据库
6. 显示访问信息

### 方案三：手动修复本地开发环境

如果需要在本地开发环境运行：

```bash
# 进入后端目录
cd backend

# 安装 PHP 8.2+
# macOS: brew install php@8.2
# Ubuntu/Debian: sudo apt install php8.2 php8.2-mysql php8.2-mbstring

# 安装 Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 安装依赖
composer install

# 复制环境文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate

# 设置存储目录权限
mkdir -p storage storage/framework storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage bootstrap/cache

# 运行迁移
php artisan migrate

# 启动开发服务器
php artisan serve
```

## 维修记录功能验证

一旦系统运行，可以通过以下方式验证维修记录功能：

### 1. API 端点测试
```
GET    /maintenance           # 获取维修记录列表
POST   /maintenance           # 创建维修记录
GET    /maintenance/{id}      # 获取详情
PUT    /maintenance/{id}      # 更新记录
DELETE /maintenance/{id}      # 删除记录
POST   /maintenance/{id}/assign   # 分配维修人员
POST   /maintenance/{id}/complete # 完成维修
GET    /maintenance/statistics    # 获取统计
GET    /maintenance/overdue       # 获取逾期记录
GET    /assets/{asset}/maintenance/history # 资产维修历史
```

### 2. 前端界面访问
- 登录系统后，应能看到维修管理菜单
- 访问 `/maintenance` 路由查看维修记录列表
- 测试创建、编辑、分配、完成维修等功能

## 常见问题排查

### Q1: 仍然看到 11133 错误
**可能原因**: 数据库迁移未正确执行
**解决方案**:
```bash
# 重新运行迁移
docker-compose exec backend php artisan migrate:fresh --force

# 或检查迁移状态
docker-compose exec backend php artisan migrate:status
```

### Q2: 无法连接到数据库
**可能原因**: `.env` 中的数据库配置错误
**解决方案**:
1. 检查 `.env` 中的数据库连接配置
2. 确保 MySQL 容器正在运行
3. 检查端口是否被占用

### Q3: 前端无法访问后端 API
**可能原因**: CORS 配置或代理设置问题
**解决方案**:
1. 检查 `VITE_API_URL` 配置
2. 确保前端正确配置了代理
3. 检查 Nginx 配置

### Q4: 微信通知功能不工作
**可能原因**: 微信配置未启用
**解决方案**:
1. 在 `.env` 中设置 `WECHAT_ENABLED=true`
2. 配置正确的 `WECHAT_WEBHOOK_URL`
3. 确保网络可以访问企业微信

## 访问信息

成功启动后，可以通过以下地址访问：

- **前端界面**: http://localhost
- **后端 API**: http://localhost:8000
- **默认管理员账号**: 
  - 邮箱: admin@example.com
  - 密码: admin123

## 后续维护

### 更新代码后
```bash
# 重建并重启服务
docker-compose down
docker-compose build
docker-compose up -d
docker-compose exec backend php artisan migrate --force
```

### 查看日志
```bash
# 查看所有服务日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs -f backend
docker-compose logs -f mysql
```

### 备份数据库
```bash
# 导出数据库
docker-compose exec mysql mysqldump -u snipe_user -psnipe_password snipe_cn > backup.sql

# 导入数据库
docker-compose exec -T mysql mysql -u snipe_user -psnipe_password snipe_cn < backup.sql
```

## 联系支持

如果问题仍然存在，请提供以下信息：

1. 错误日志：`docker-compose logs backend`
2. 系统信息：`docker --version` 和 `docker-compose --version`
3. 环境配置：`.env` 文件内容（去除敏感信息）
4. 错误截图或完整错误信息