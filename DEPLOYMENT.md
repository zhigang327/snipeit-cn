# Snipe-CN 部署文档

## 目录
- [系统要求](#系统要求)
- [快速开始](#快速开始)
- [详细部署步骤](#详细部署步骤)
- [配置说明](#配置说明)
- [常见问题](#常见问题)
- [备份与恢复](#备份与恢复)
- [性能优化](#性能优化)

## 系统要求

### 硬件要求
- CPU: 2核及以上
- 内存: 4GB及以上(推荐8GB)
- 磁盘: 20GB及以上可用空间
- 网络: 支持Docker端口映射

### 软件要求
- Docker 20.10+
- Docker Compose 2.0+
- 操作系统: Linux / macOS / Windows with WSL2

## 快速开始

### 1. 克隆项目
```bash
git clone <repository-url>
cd snipe
```

### 2. 配置环境变量
```bash
cp .env.example .env
```

编辑 `.env` 文件,根据实际情况修改配置:
```bash
# 数据库配置
DB_DATABASE=snipe_cn
DB_USERNAME=snipe_user
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password

# 端口配置(如有端口冲突可修改)
BACKEND_PORT=8000
FRONTEND_PORT=5173
NGINX_PORT=80
```

### 3. 启动服务
```bash
# 构建并启动所有服务
docker-compose up -d

# 查看服务状态
docker-compose ps
```

### 4. 初始化数据库
```bash
# 运行数据库迁移
docker-compose exec backend php artisan migrate

# 填充初始数据
docker-compose exec backend php artisan db:seed
```

### 5. 访问系统
- 前端地址: http://localhost
- 默认管理员账号: `admin@example.com` / `admin123`

**首次登录后请立即修改默认密码!**

## 详细部署步骤

### 步骤1: 环境准备

#### Linux/macOS
```bash
# 检查Docker版本
docker --version
docker-compose --version

# 如果未安装,参考官方文档安装
# Docker: https://docs.docker.com/engine/install/
# Docker Compose: https://docs.docker.com/compose/install/
```

#### Windows
1. 下载并安装 Docker Desktop for Windows
2. 启用 WSL 2 后端
3. 重启计算机

### 步骤2: 项目配置

#### 修改环境变量
```bash
# 编辑 .env 文件
nano .env
```

关键配置项说明:

| 配置项 | 说明 | 默认值 | 建议修改 |
|--------|------|--------|----------|
| DB_PASSWORD | 数据库用户密码 | snipe_password | 修改为强密码 |
| DB_ROOT_PASSWORD | 数据库root密码 | root_password | 修改为强密码 |
| APP_URL | 应用URL | http://localhost | 生产环境需修改 |
| MAIL_* | 邮件配置 | - | 配置以启用邮件通知 |
| CURRENCY | 货币单位 | CNY | 根据需要修改 |

#### 配置HTTPS(生产环境)
编辑 `docker/nginx/conf.d/default.conf`:
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;

    # ... 其他配置
}

server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### 步骤3: 构建镜像
```bash
# 仅构建(不启动)
docker-compose build

# 或直接启动(自动构建)
docker-compose up -d
```

首次构建可能需要10-20分钟,取决于网络速度。

### 步骤4: 数据库初始化
```bash
# 等待MySQL完全启动
docker-compose logs -f mysql

# 看到 "ready for connections" 后按 Ctrl+C 退出

# 运行迁移
docker-compose exec backend php artisan migrate --force

# 填充测试数据
docker-compose exec backend php artisan db:seed --force
```

### 步骤5: 验证部署
```bash
# 检查所有服务状态
docker-compose ps

# 应该看到所有服务都是 "Up" 状态
# backend      Up      0.0.0.0:8000->8000/tcp
# frontend     Up      0.0.0.0:5173->5173/tcp
# mysql        Up      0.0.0.0:3306->3306/tcp
# nginx        Up      0.0.0.0:80->80/tcp
# redis        Up      0.0.0.0:6379->6379/tcp
```

### 步骤6: 访问应用
打开浏览器访问: http://localhost

使用默认账号登录:
- 邮箱: `admin@example.com`
- 密码: `admin123`

## 配置说明

### 环境变量完整说明

```bash
# 应用配置
APP_NAME=Snipe-CN                    # 应用名称
APP_ENV=production                    # 环境: production/development
APP_DEBUG=false                       # 调试模式(生产环境设为false)
APP_URL=http://localhost              # 应用URL
APP_TIMEZONE=Asia/Shanghai            # 时区

# 数据库配置
DB_HOST=mysql                         # 数据库主机
DB_PORT=3306                          # 数据库端口
DB_DATABASE=snipe_cn                 # 数据库名
DB_USERNAME=snipe_user               # 数据库用户
DB_PASSWORD=your_password            # 数据库密码
DB_ROOT_PASSWORD=your_root_password   # Root密码

# Redis配置
REDIS_HOST=redis                     # Redis主机
REDIS_PORT=6379                      # Redis端口

# 端口配置
BACKEND_PORT=8000                    # 后端API端口
FRONTEND_PORT=5173                   # 前端开发端口
NGINX_PORT=80                        # Nginx端口
DB_PORT=3306                         # 数据库端口
REDIS_PORT_LOCAL=6379                # Redis端口

# 前端配置
VITE_API_URL=http://localhost:8000   # API地址

# 邮件配置(可选)
MAIL_MAILER=smtp                     # 邮件驱动
MAIL_HOST=smtp.qq.com                # SMTP服务器
MAIL_PORT=587                        # SMTP端口
MAIL_USERNAME=your@qq.com            # SMTP用户名
MAIL_PASSWORD=your_password          # SMTP密码
MAIL_ENCRYPTION=tls                  # 加密方式
MAIL_FROM_ADDRESS=noreply@snipe.cn   # 发件人地址
MAIL_FROM_NAME=Snipe-CN              # 发件人名称

# 系统配置
CURRENCY=CNY                         # 货币单位
DATE_FORMAT=Y-m-d                    # 日期格式
TIME_FORMAT=H:i:s                    # 时间格式
```

### 邮件配置示例

#### QQ邮箱
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.qq.com
MAIL_PORT=587
MAIL_USERNAME=your@qq.com
MAIL_PASSWORD=your_qq_mail_code      # QQ邮箱授权码,不是QQ密码
MAIL_ENCRYPTION=tls
```

#### 企业微信邮箱
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.exmail.qq.com
MAIL_PORT=465
MAIL_USERNAME=your@company.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=ssl
```

#### 163邮箱
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.163.com
MAIL_PORT=465
MAIL_USERNAME=your@163.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=ssl
```

## 常见问题

### 1. 端口被占用
**问题**: `Error starting userland proxy: listen tcp 0.0.0.0:80: bind: address already in use`

**解决**: 修改 `.env` 文件中的端口配置
```bash
NGINX_PORT=8080  # 改为其他端口
```

### 2. 数据库连接失败
**问题**: `SQLSTATE[HY000] [2002] Connection refused`

**解决**:
```bash
# 检查MySQL是否启动
docker-compose ps mysql

# 重启MySQL
docker-compose restart mysql

# 等待30秒后再尝试
docker-compose exec backend php artisan migrate
```

### 3. 前端无法访问后端API
**问题**: Network Error 或 CORS错误

**解决**:
1. 检查 `.env` 中的 `VITE_API_URL` 配置
2. 确保backend服务正常运行: `docker-compose ps backend`
3. 查看backend日志: `docker-compose logs backend`

### 4. 镜像构建失败
**问题**: 构建过程中网络超时或下载失败

**解决**:
```bash
# 清理缓存重试
docker-compose build --no-cache

# 或使用国内镜像加速
# 编辑 /etc/docker/daemon.json
{
  "registry-mirrors": ["https://mirror.ccs.tencentyun.com"]
}
```

### 5. 无法登录
**问题**: 输入正确账号密码仍无法登录

**解决**:
```bash
# 重置管理员密码
docker-compose exec backend php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first()
>>> $user->password = bcrypt('new_password')
>>> $user->save()
>>> exit
```

### 6. 磁盘空间不足
**问题**: Docker占用过多磁盘空间

**解决**:
```bash
# 清理未使用的镜像和容器
docker system prune -a

# 清理卷
docker volume prune

# 查看磁盘占用
docker system df
```

## 备份与恢复

### 备份数据库
```bash
# 创建备份目录
mkdir -p backups

# 备份数据库
docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > backups/backup_$(date +%Y%m%d_%H%M%S).sql

# 备份上传的文件
docker run --rm -v $(pwd)/backups:/backup -v snipe-cn-mysql_data:/data alpine tar czf /backup/mysql_data_$(date +%Y%m%d_%H%M%S).tar.gz -C /data .
```

### 自动备份脚本
创建 `scripts/backup.sh`:
```bash
#!/bin/bash

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backups"
mkdir -p $BACKUP_DIR

# 备份数据库
docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > $BACKUP_DIR/backup_${DATE}.sql

# 保留最近7天的备份
find $BACKUP_DIR -name "backup_*.sql" -mtime +7 -delete

echo "Backup completed: backup_${DATE}.sql"
```

添加到crontab:
```bash
# 每天凌晨2点备份
0 2 * * * /path/to/snipe/scripts/backup.sh
```

### 恢复数据库
```bash
# 恢复备份
docker-compose exec -T mysql mysql -u root -p${DB_ROOT_PASSWORD} snipe_cn < backups/backup_20240101_020000.sql
```

## 性能优化

### MySQL优化
编辑 `docker/mysql/my.cnf`:
```ini
[mysqld]
innodb_buffer_pool_size = 1G          # 根据内存调整
innodb_log_file_size = 256M
max_connections = 500
query_cache_size = 64M
```

### PHP优化
编辑 `docker/php/php.ini`:
```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
```

### Redis优化
启用Redis缓存(已默认启用):
```bash
# 清理缓存
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan route:clear
```

## 监控与日志

### 查看日志
```bash
# 查看所有服务日志
docker-compose logs

# 查看特定服务日志
docker-compose logs backend
docker-compose logs nginx
docker-compose logs mysql

# 实时查看日志
docker-compose logs -f
```

### 资源监控
```bash
# 查看容器资源使用
docker stats

# 查看磁盘使用
docker system df
```

## 安全建议

1. **修改默认密码**: 首次部署后立即修改所有默认密码
2. **启用HTTPS**: 生产环境务必配置SSL证书
3. **定期备份**: 设置自动备份任务
4. **限制访问**: 使用防火墙限制不必要的外部访问
5. **更新依赖**: 定期更新Docker镜像和依赖包
6. **监控日志**: 定期检查异常日志

## 故障排查

```bash
# 服务状态检查
docker-compose ps

# 网络检查
docker network inspect snipe-cn_snipe-cn-network

# 进入容器调试
docker-compose exec backend bash
docker-compose exec mysql bash

# 重启服务
docker-compose restart
docker-compose restart backend

# 完全重建(谨慎使用)
docker-compose down -v
docker-compose up -d
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force
```

## 技术支持

如有问题,请提交Issue或联系技术支持团队。

## 更新升级

```bash
# 拉取最新代码
git pull origin main

# 重新构建镜像
docker-compose build

# 重启服务
docker-compose up -d

# 运行数据库迁移
docker-compose exec backend php artisan migrate --force

# 清理缓存
docker-compose exec backend php artisan cache:clear
```
