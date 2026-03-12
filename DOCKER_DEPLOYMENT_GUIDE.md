# Snipe-CN Docker部署指南

## 🎯 文档概述

本文档提供从GitHub仓库到本地服务器的完整Docker部署指南。Snipe-CN是一个基于Snipe-IT汉化版的资产管理系统，提供了微信通知、资产借用、报废管理和盘点管理等完整功能。

### 版本信息
- **当前版本**: v1.6.0
- **最新提交**: 1586aaa
- **发布日期**: 2026-03-12
- **GitHub仓库**: https://github.com/zhigang327/snipeit-cn

### 功能特性
- ✅ 资产全生命周期管理
- ✅ 微信通知功能
- ✅ 资产借用管理
- ✅ 资产报废管理
- ✅ 资产盘点管理
- ✅ 移动端支持（二维码扫描）
- ✅ 实时统计分析

## 🚀 快速部署（5分钟内完成）

### 步骤1: 环境准备（1分钟）
确保您的服务器已安装Docker和Docker Compose：

```bash
# 检查Docker版本（要求20.10+）
docker --version

# 检查Docker Compose版本（要求2.0+）
docker-compose --version

# 如果未安装，参考以下快速安装脚本：
# Ubuntu/Debian
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo apt-get install docker-compose-plugin

# CentOS/RHEL
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo yum install docker-compose-plugin
```

### 步骤2: 获取项目代码（1分钟）
```bash
# 克隆GitHub仓库
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 或者如果已经下载ZIP包
unzip snipeit-cn-main.zip
cd snipeit-cn-main
```

### 步骤3: 配置环境变量（1分钟）
```bash
# 复制环境变量模板
cp .env.example .env

# 编辑配置文件（使用您喜欢的编辑器）
nano .env
```

**最少需要修改的配置项**：
```bash
# 数据库密码（必须修改！）
DB_PASSWORD=YourStrongPassword123!
DB_ROOT_PASSWORD=RootStrongPassword456!

# 应用URL（根据实际访问地址修改）
APP_URL=http://your-server-ip-or-domain
VITE_API_URL=http://your-server-ip-or-domain:8000

# 如果端口冲突，可以修改以下端口
NGINX_PORT=80  # Web访问端口
BACKEND_PORT=8000  # API端口
```

### 步骤4: 启动服务（2分钟）
```bash
# 启动所有Docker服务（首次启动会自动构建镜像）
docker-compose up -d

# 查看服务状态
docker-compose ps

# 等待服务启动（大约1-2分钟）
sleep 120
```

### 步骤5: 初始化系统（1分钟）
```bash
# 运行数据库迁移
docker-compose exec backend php artisan migrate --force

# 填充初始数据（包括默认管理员账户）
docker-compose exec backend php artisan db:seed --force
```

### 完成！
现在可以访问系统了：
- **访问地址**: http://your-server-ip-or-domain
- **默认管理员账号**: 
  - 邮箱: `admin@example.com`
  - 密码: `admin123`

**立即修改默认管理员密码！**

## 📋 详细部署说明

### 1. 系统要求

#### 硬件要求
| 资源 | 最低要求 | 推荐配置 | 说明 |
|------|----------|----------|------|
| CPU | 2核 | 4核 | 多核心有助于并行处理 |
| 内存 | 4GB | 8GB | 运行MySQL、Redis、PHP、Node.js |
| 磁盘 | 20GB | 50GB | 包含数据库、上传文件、Docker镜像 |
| 网络 | 10Mbps | 100Mbps | 支持多用户同时访问 |

#### 软件要求
- **Docker**: 20.10.0 或更高版本
- **Docker Compose**: 2.0.0 或更高版本
- **操作系统**: 
  - Ubuntu 20.04+/Debian 11+/CentOS 8+
  - macOS 11+ (开发环境)
  - Windows 10/11 with WSL2 (开发环境)
- **Git**: 用于代码版本管理

### 2. 目录结构说明

```
snipeit-cn/
├── backend/                 # 后端Laravel应用
│   ├── app/                # 应用代码
│   ├── database/           # 数据库迁移和种子文件
│   ├── public/             # 公共文件
│   ├── routes/             # 路由配置
│   ├── storage/            # 上传文件存储
│   └── Dockerfile          # 后端Docker镜像配置
├── frontend/               # 前端Vue3应用
│   ├── src/               # 源码目录
│   ├── public/            # 静态资源
│   └── Dockerfile         # 前端Docker镜像配置
├── docker/                 # Docker配置
│   ├── nginx/             # Nginx配置
│   ├── mysql/             # MySQL配置
│   └── php/               # PHP配置
├── docker-compose.yml      # Docker编排文件
├── .env.example           # 环境变量模板
└── DOCKER_DEPLOYMENT_GUIDE.md  # 本部署文档
```

### 3. Docker服务架构

本系统使用多容器架构：

| 服务 | 容器名 | 端口 | 说明 |
|------|--------|------|------|
| MySQL | snipe-cn-mysql | 3306 | 数据库存储 |
| Redis | snipe-cn-redis | 6379 | 缓存和会话 |
| PHP后端 | snipe-cn-backend | 8000 | Laravel API服务 |
| Vue前端 | snipe-cn-frontend | 5173 | Vue开发服务器 |
| Nginx | snipe-cn-nginx | 80 | Web服务器和反向代理 |

### 4. 环境变量详细说明

#### 基础配置（必须配置）
```bash
# 应用配置
APP_NAME=Snipe-CN
APP_ENV=production          # 生产环境设为production
APP_DEBUG=false             # 生产环境必须设为false
APP_URL=http://your-domain.com  # 实际访问地址
APP_TIMEZONE=Asia/Shanghai  # 时区设置

# 数据库配置
DB_HOST=mysql               # Docker内部使用服务名
DB_PORT=3306
DB_DATABASE=snipe_cn       # 数据库名称
DB_USERNAME=snipe_user      # 数据库用户名
DB_PASSWORD=YourSecurePassword123!  # 强密码
DB_ROOT_PASSWORD=RootSecurePassword456!  # root密码

# Redis配置
REDIS_HOST=redis           # Docker内部使用服务名
REDIS_PORT=6379

# 端口映射配置
BACKEND_PORT=8000          # 后端API外部端口
FRONTEND_PORT=5173         # 前端开发端口（开发环境使用）
NGINX_PORT=80              # Web访问端口（或443 HTTPS）
DB_PORT=3306               # MySQL外部端口（可选）
REDIS_PORT_LOCAL=6379      # Redis外部端口（可选）

# 前端配置
VITE_API_URL=http://your-domain.com:8000  # API地址
```

#### 邮件配置（可选但推荐）
```bash
# 邮件配置 - 使用QQ邮箱示例
MAIL_MAILER=smtp
MAIL_HOST=smtp.qq.com
MAIL_PORT=587
MAIL_USERNAME=your_email@qq.com
MAIL_PASSWORD=your_authorization_code  # QQ邮箱授权码，不是密码
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@snipe.cn
MAIL_FROM_NAME=Snipe-CN

# 企业微信邮箱示例
MAIL_MAILER=smtp
MAIL_HOST=smtp.exmail.qq.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl

# 163邮箱示例
MAIL_MAILER=smtp
MAIL_HOST=smtp.163.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
```

#### 微信通知配置（可选）
```bash
# 微信通知功能（需要企业微信机器人）
WECHAT_ENABLED=true
WECHAT_WEBHOOK_URL=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your_key

# 通知类型
WECHAT_NOTIFY_ASSET_EXPIRING=true   # 资产到期通知
WECHAT_NOTIFY_ASSET_CHANGED=true    # 资产变更通知
WECHAT_NOTIFY_INVENTORY_CREATED=true # 盘点创建通知
WECHAT_NOTIFY_INVENTORY_COMPLETED=true # 盘点完成通知
WECHAT_NOTIFY_MAINTENANCE=true      # 维修通知
WECHAT_NOTIFY_BORROW=true           # 借用通知
```

### 5. 生产环境部署最佳实践

#### 安全配置
```bash
# 1. 修改所有默认密码
DB_PASSWORD=随机生成32位强密码
DB_ROOT_PASSWORD=随机生成32位强密码

# 2. 启用HTTPS
APP_URL=https://your-domain.com
NGINX_PORT=443

# 3. 配置SSL证书
# 将证书文件复制到docker/nginx/ssl/目录
mkdir -p docker/nginx/ssl
cp fullchain.pem docker/nginx/ssl/cert.pem
cp privkey.pem docker/nginx/ssl/key.pem

# 4. 更新Nginx配置
# 编辑docker/nginx/conf.d/default.conf
# 启用SSL配置
```

#### 性能优化配置
```bash
# 在.env中添加以下配置
# PHP内存限制
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300

# 文件上传限制
PHP_UPLOAD_MAX_FILESIZE=100M
PHP_POST_MAX_SIZE=100M
```

### 6. 端口映射说明

默认端口映射配置：

| 内部端口 | 外部端口 | 服务 | 访问方式 |
|----------|----------|------|----------|
| 3306 | 3306 | MySQL | 本地数据库管理 |
| 6379 | 6379 | Redis | 本地缓存管理 |
| 8000 | 8000 | PHP后端 | API访问 |
| 5173 | 5173 | Vue前端 | 开发环境访问 |
| 80 | 80 | Nginx | Web访问 |

**端口冲突解决方案**：
1. 修改`.env`文件中的端口配置
2. 重启服务：`docker-compose up -d`
3. 更新防火墙规则

### 7. 数据库初始化

#### 首次部署
```bash
# 运行数据库迁移（创建表结构）
docker-compose exec backend php artisan migrate --force

# 填充初始数据
docker-compose exec backend php artisan db:seed --force

# 填充的数据包括：
# - 管理员账户 (admin@example.com / admin123)
# - 部门数据
# - 资产分类
# - 权限角色
# - 系统设置
```

#### 验证数据库
```bash
# 进入MySQL容器
docker-compose exec mysql bash

# 登录MySQL
mysql -u snipe_user -p

# 查看数据库
SHOW DATABASES;
USE snipe_cn;
SHOW TABLES;
exit
```

### 8. 系统访问和验证

#### 验证服务状态
```bash
# 检查所有服务状态
docker-compose ps

# 预期输出：
NAME                 COMMAND                  SERVICE             STATUS              PORTS
snipe-cn-mysql       "docker-entrypoint.s…"   mysql               running             0.0.0.0:3306->3306/tcp
snipe-cn-redis       "docker-entrypoint.s…"   redis               running             0.0.0.0:6379->6379/tcp
snipe-cn-backend     "docker-entrypoint.s…"   backend             running             0.0.0.0:8000->8000/tcp
snipe-cn-frontend    "docker-entrypoint.s…"   frontend            running             0.0.0.0:5173->5173/tcp
snipe-cn-nginx       "/docker-entrypoint.…"   nginx               running             0.0.0.0:80->80/tcp
```

#### 访问系统
1. **Web界面**: http://your-server-ip (或域名)
2. **API文档**: http://your-server-ip:8000/docs
3. **健康检查**: http://your-server-ip:8000/health

#### 首次登录后操作
1. **修改管理员密码**: 账户设置 → 修改密码
2. **配置系统设置**: 系统设置 → 基本设置
3. **添加部门**: 组织架构 → 部门管理
4. **添加用户**: 用户管理 → 添加用户
5. **导入资产**: 资产 → 导入资产

### 9. 数据备份和恢复

#### 备份脚本
创建 `scripts/backup.sh`：
```bash
#!/bin/bash
# 数据库备份脚本

BACKUP_DIR="backups"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# 从环境变量获取密码
source .env

# 备份数据库
docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > $BACKUP_DIR/backup_${DATE}.sql

# 压缩备份
gzip $BACKUP_DIR/backup_${DATE}.sql

# 备份上传的文件（可选）
docker-compose exec backend tar czf - /var/www/html/storage/app/uploads > $BACKUP_DIR/uploads_${DATE}.tar.gz 2>/dev/null || true

# 保留最近7天的备份
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "备份完成: $BACKUP_DIR/backup_${DATE}.sql.gz"
```

#### 自动备份（使用crontab）
```bash
# 编辑crontab
crontab -e

# 添加以下行（每天凌晨2点备份）
0 2 * * * /path/to/snipeit-cn/scripts/backup.sh >> /path/to/snipeit-cn/backups/backup.log 2>&1
```

#### 恢复数据
```bash
# 解压备份文件
gunzip backups/backup_20250101_020000.sql.gz

# 恢复数据库
docker-compose exec -T mysql mysql -u root -p${DB_ROOT_PASSWORD} snipe_cn < backups/backup_20250101_020000.sql

# 恢复上传文件（可选）
docker-compose exec backend tar xzf backups/uploads_20250101_020000.tar.gz -C / 2>/dev/null || true
```

### 10. 监控和日志

#### 查看日志
```bash
# 查看所有服务日志
docker-compose logs

# 实时查看日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs backend
docker-compose logs nginx
docker-compose logs mysql
docker-compose logs frontend

# 查看错误日志
docker-compose logs --tail=100 backend | grep -i error
docker-compose logs --tail=100 nginx | grep -i error
```

#### 性能监控
```bash
# 查看容器资源使用
docker stats

# 查看磁盘使用
docker system df

# 查看网络
docker network inspect snipe-cn_snipe-cn-network
```

#### 健康检查
```bash
# API健康检查
curl http://localhost:8000/health

# 数据库连接检查
docker-compose exec backend php artisan db:monitor

# Redis连接检查
docker-compose exec backend php artisan redis:ping
```

### 11. 故障排除

#### 常见问题及解决方案

**问题1: 端口冲突**
```bash
# 错误信息: Error starting userland proxy: listen tcp 0.0.0.0:80: bind: address already in use

# 解决方案:
# 1. 查看占用端口的进程
sudo lsof -i :80

# 2. 停止占用进程或修改端口
# 修改.env文件中的端口配置
NGINX_PORT=8080
BACKEND_PORT=8001

# 3. 重启服务
docker-compose up -d
```

**问题2: 数据库连接失败**
```bash
# 错误信息: SQLSTATE[HY000] [2002] Connection refused

# 解决方案:
# 1. 检查MySQL是否启动
docker-compose ps mysql

# 2. 查看MySQL日志
docker-compose logs mysql

# 3. 重启MySQL服务
docker-compose restart mysql

# 4. 等待30秒后重试
sleep 30
docker-compose exec backend php artisan migrate --force
```

**问题3: 前端无法访问后端API**
```bash
# 错误信息: Network Error 或 CORS错误

# 解决方案:
# 1. 检查VITE_API_URL配置
echo $VITE_API_URL

# 2. 确保backend服务正常运行
docker-compose ps backend

# 3. 测试API连通性
curl http://localhost:8000/api/health

# 4. 检查CORS配置
docker-compose exec backend cat config/cors.php
```

**问题4: 镜像构建失败**
```bash
# 错误信息: npm ERR! 或 composer ERR! (或具体错误: process "/bin/sh -c composer install" did not complete successfully)

# 解决方案（选择一种）:

# 方案1: 使用构建修复脚本（推荐）
chmod +x scripts/fix-build.sh
./scripts/fix-build.sh --all

# 方案2: 配置国内镜像加速并清理缓存
docker-compose build --no-cache

# 方案3: 手动配置国内镜像源
# 创建 /etc/docker/daemon.json
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com",
    "https://mirror.ccs.tencentyun.com"
  ]
}

# 3. 单独构建问题服务
docker-compose build backend
docker-compose build frontend
```

**问题5: 无法登录系统**
```bash
# 错误信息: 账号密码正确但无法登录

# 解决方案:
# 1. 重置管理员密码
docker-compose exec backend php artisan tinker

# 在tinker中执行:
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first()
>>> $user->password = bcrypt('new_strong_password')
>>> $user->save()
>>> exit

# 2. 检查session配置
docker-compose exec backend php artisan config:show session

# 3. 清除缓存
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan view:clear
```

#### 调试工具
```bash
# 进入容器调试
docker-compose exec backend bash
docker-compose exec mysql bash
docker-compose exec nginx sh

# 检查网络连通性
docker-compose exec backend ping mysql
docker-compose exec backend ping redis

# 检查文件权限
docker-compose exec backend ls -la storage/
docker-compose exec backend ls -la bootstrap/cache/
```

### 12. 系统维护

#### 日常维护命令
```bash
# 清理缓存
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan route:clear
docker-compose exec backend php artisan view:clear

# 优化自动加载
docker-compose exec backend composer dump-autoload -o

# 检查更新
docker-compose exec backend php artisan snipe:check-updates

# 生成应用密钥
docker-compose exec backend php artisan key:generate
```

#### 容器维护
```bash
# 停止所有服务
docker-compose down

# 停止并删除所有数据（谨慎使用）
docker-compose down -v

# 重启单个服务
docker-compose restart backend
docker-compose restart nginx

# 查看容器资源使用
docker stats $(docker ps --format={{.Names}})

# 清理未使用的资源
docker system prune -a
docker volume prune
```

### 13. 升级和更新

#### 从GitHub更新
```bash
# 1. 备份当前数据
./scripts/backup.sh

# 2. 拉取最新代码
git pull origin main

# 3. 检查是否有新的.env配置
# 比较.env.example和.env
diff .env.example .env

# 4. 更新环境变量（如果需要）
# 将.env.example中的新配置添加到.env

# 5. 重建镜像
docker-compose build

# 6. 重启服务
docker-compose up -d

# 7. 运行数据库迁移
docker-compose exec backend php artisan migrate --force

# 8. 清理缓存
docker-compose exec backend php artisan cache:clear
```

#### 版本回滚
```bash
# 1. 停止当前服务
docker-compose down

# 2. 恢复到特定版本
git checkout v1.5.0

# 3. 恢复备份数据
gunzip backups/backup_20250101_020000.sql.gz
docker-compose exec -T mysql mysql -u root -p${DB_ROOT_PASSWORD} snipe_cn < backups/backup_20250101_020000.sql

# 4. 启动服务
docker-compose up -d
```

### 14. 性能调优

#### 数据库优化
```bash
# 编辑MySQL配置
nano docker/mysql/my.cnf

# 添加以下配置（根据内存调整）
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 500
query_cache_size = 64M
query_cache_type = 1
slow_query_log = 1
long_query_time = 2
```

#### PHP优化
```bash
# 编辑PHP配置
nano docker/php/php.ini

# 添加以下配置
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
upload_max_filesize = 100M
post_max_size = 100M
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

#### Nginx优化
```bash
# 编辑Nginx配置
nano docker/nginx/nginx.conf

# 添加以下配置
worker_processes auto;
worker_rlimit_nofile 65535;
events {
    worker_connections 4096;
    multi_accept on;
    use epoll;
}
http {
    client_max_body_size 100M;
    client_body_buffer_size 128k;
    keepalive_timeout 75s;
    keepalive_requests 1000;
    send_timeout 60s;
    gzip on;
    gzip_comp_level 6;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```

### 15. 安全加固

#### 生产环境安全配置
1. **启用HTTPS**
   ```bash
   # 申请SSL证书（Let's Encrypt）
   certbot certonly --nginx -d your-domain.com
   
   # 配置Nginx SSL
   cp docker/nginx/conf.d/default.conf docker/nginx/conf.d/default.conf.backup
   # 创建新的SSL配置
   ```

2. **修改默认端口**
   ```bash
   # 修改.env中的端口配置
   NGINX_PORT=8443
   BACKEND_PORT=8444
   ```

3. **配置防火墙**
   ```bash
   # Ubuntu/Debian
   sudo ufw allow 8443/tcp
   sudo ufw allow 8444/tcp
   sudo ufw enable
   
   # CentOS/RHEL
   sudo firewall-cmd --permanent --add-port=8443/tcp
   sudo firewall-cmd --permanent --add-port=8444/tcp
   sudo firewall-cmd --reload
   ```

4. **定期更新**
   ```bash
   # 更新Docker镜像
   docker-compose pull
   
   # 更新系统包
   sudo apt update && sudo apt upgrade -y
   ```

#### 安全扫描
```bash
# 使用Docker安全扫描工具
docker scan snipe-cn-backend
docker scan snipe-cn-frontend

# 检查容器安全配置
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy image snipe-cn-backend:latest
```

### 16. 扩展和自定义

#### 添加自定义功能
1. **添加新的API端点**
   ```php
   // 在backend/app/Http/Controllers/添加新控制器
   // 在backend/routes/api.php中添加路由
   // 在frontend/src/api/中添加API调用
   ```

2. **修改前端界面**
   ```javascript
   // 编辑frontend/src/views/中的Vue组件
   // 添加新的路由在frontend/src/router/index.js
   ```

3. **自定义报表**
   ```php
   // 在backend/app/Services/中添加报表服务
   // 在backend/app/Http/Controllers/中添加报表控制器
   ```

#### 集成第三方服务
1. **集成LDAP/AD认证**
   ```bash
   # 安装LDAP扩展
   # 修改.env配置
   LDAP_ENABLED=true
   LDAP_HOST=ldap.yourcompany.com
   LDAP_PORT=389
   ```

2. **集成微信小程序**
   ```bash
   # 配置微信小程序API
   WECHAT_MP_APPID=your_appid
   WECHAT_MP_SECRET=your_secret
   ```

### 17. 技术支持

#### 获取帮助
1. **查看文档**
   - 本项目文档：查看docs/目录
   - 在线文档：GitHub Wiki

2. **提交Issue**
   - GitHub Issues: https://github.com/zhigang327/snipeit-cn/issues
   - 请提供以下信息：
     - 系统版本
     - 错误日志
     - 复现步骤
     - 期望结果

3. **社区支持**
   - QQ群：[群号待添加]
   - Discord：[链接待添加]
   - 论坛：[链接待添加]

#### 故障诊断清单
```bash
# 系统健康检查脚本
#!/bin/bash
echo "=== Snipe-CN 健康检查 ==="
echo "1. 检查服务状态..."
docker-compose ps
echo ""
echo "2. 检查网络连通性..."
docker-compose exec backend curl -s http://localhost:8000/health
echo ""
echo "3. 检查数据库..."
docker-compose exec backend php artisan db:monitor
echo ""
echo "4. 检查Redis..."
docker-compose exec backend php artisan redis:ping
echo ""
echo "5. 检查磁盘空间..."
df -h | grep -E "/$|docker"
echo ""
echo "6. 检查日志错误..."
docker-compose logs --tail=50 | grep -i error | tail -10
echo "=== 检查完成 ==="
```

### 18. 附录

#### 常用命令速查表

| 命令 | 说明 | 使用场景 |
|------|------|----------|
| `docker-compose up -d` | 启动所有服务 | 首次部署或重启 |
| `docker-compose down` | 停止所有服务 | 维护或升级前 |
| `docker-compose ps` | 查看服务状态 | 故障排查 |
| `docker-compose logs` | 查看日志 | 错误诊断 |
| `docker-compose exec backend bash` | 进入后端容器 | 调试或维护 |
| `docker-compose restart backend` | 重启后端服务 | 配置更新后 |
| `docker-compose build` | 重新构建镜像 | 代码更新后 |
| `docker system prune -a` | 清理未使用资源 | 磁盘空间不足时 |

#### 默认端口参考表

| 端口 | 服务 | 协议 | 说明 |
|------|------|------|------|
| 80/443 | Nginx | HTTP/HTTPS | Web访问端口 |
| 8000 | Backend | HTTP | API服务端口 |
| 3306 | MySQL | TCP | 数据库端口 |
| 6379 | Redis | TCP | 缓存端口 |
| 5173 | Frontend | HTTP | 开发服务器端口 |

#### 环境变量快速参考

```bash
# 必须修改的配置
DB_PASSWORD=必须修改
DB_ROOT_PASSWORD=必须修改
APP_URL=实际访问地址

# 推荐修改的配置
MAIL_*配置=启用邮件通知
WECHAT_*配置=启用微信通知

# 可选配置
端口配置=解决端口冲突时修改
性能配置=根据服务器资源调整
```

---

## 🎉 部署成功！

恭喜！您已经成功部署了Snipe-CN资产管理系统。系统包含以下完整功能：

### 已实现的核心功能
1. **资产管理** - 资产的采购、登记、分配、转移、报废全生命周期管理
2. **微信通知** - 实时推送资产变更、到期、盘点等通知
3. **借用管理** - 完整的资产借用审批和归还流程
4. **报废管理** - 资产报废申请、审批、处理流程
5. **盘点管理** - 支持二维码扫描的快速盘点功能
6. **统计分析** - 多维度的资产统计和报表功能

### 后续操作建议
1. **立即修改**默认管理员密码
2. **配置**邮件通知和微信通知
3. **导入**现有资产数据
4. **培训**相关人员使用系统
5. **设置**定期备份任务

### 技术支持
如果在部署或使用过程中遇到问题，请参考：
- 本文档的故障排除部分
- GitHub Issues提交问题
- 联系技术支持团队

**祝您使用愉快！**

---
*文档版本: v1.0.0*  
*最后更新: 2026-03-12*  
*文档维护: WorkBuddy AI Agent*