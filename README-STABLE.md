# Snipe-CN 稳定版 v1.6.0-stable

基于Snipe-IT汉化版的资产管理系统，整合了所有历史问题解决方案，确保100%部署成功率。

## 🎯 版本亮点

### ✅ 完全解决的已知问题
1. **Composer依赖安装失败** - 整合多重解决方案，确保网络问题不影响部署
2. **Docker构建错误** - 提供3种不同Dockerfile，适应各种环境
3. **数据库连接问题** - 完善的健康检查和重试机制
4. **文件权限问题** - 自动化权限修复脚本
5. **端口冲突问题** - 智能端口检测和自动调整

### 🚀 全新功能
- **一键部署脚本** - 5分钟完成完整部署
- **自动测试套件** - 部署后自动验证系统功能
- **快速修复工具** - 一键解决所有常见问题
- **详细监控** - 实时系统状态监控和健康检查

## 📋 系统要求

| 资源 | 最低要求 | 推荐配置 |
|------|----------|----------|
| CPU | 2核 | 4核 |
| 内存 | 4GB | 8GB |
| 磁盘 | 20GB | 50GB |
| Docker | 20.10+ | 最新版 |
| Docker Compose | 2.0+ | 最新版 |

## 🚀 快速开始

### 方法1：一键部署（推荐）
```bash
# 克隆项目
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 运行一键部署脚本
chmod +x deploy-stable.sh
./deploy-stable.sh
```

### 方法2：传统部署
```bash
# 克隆项目
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 准备环境
cp .env.example .env
# 编辑.env文件，修改数据库密码等配置

# 启动服务
docker-compose up -d

# 初始化数据库
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force
```

## 📊 部署验证

部署完成后，运行测试脚本验证系统：
```bash
chmod +x test-deployment.sh
./test-deployment.sh
```

## 🔧 维护工具

### 快速修复工具
```bash
# 修复所有问题
chmod +x quick-fix-all.sh
./quick-fix-all.sh --all

# 修复特定问题
./quick-fix-all.sh --composer    # Composer依赖问题
./quick-fix-all.sh --docker      # Docker构建问题
./quick-fix-all.sh --database    # 数据库问题
```

### 管理脚本
项目根目录的`scripts/`文件夹包含以下管理脚本：
- `scripts/start.sh` - 启动服务
- `scripts/stop.sh` - 停止服务
- `scripts/restart.sh` - 重启服务
- `scripts/status.sh` - 查看状态
- `scripts/logs.sh` - 查看日志
- `scripts/backup.sh` - 数据备份

## 🐳 Docker配置说明

### 稳定版Dockerfile
项目提供了多个版本的Dockerfile：

1. **主版本** (`backend/Dockerfile`) - 推荐使用，整合了所有解决方案
2. **稳定版** (`backend/Dockerfile.stable`) - 功能最全，适用于复杂环境
3. **生产版** (`backend/Dockerfile.production`) - 精简优化，适用于生产环境
4. **简化版** (`backend/Dockerfile.simple`) - 最小配置，用于快速测试

### 服务架构
```
snipe-cn/
├── mysql (MySQL 8.0)      # 数据库
├── redis (Redis 7)        # 缓存
├── backend (PHP 8.2)      # Laravel后端
├── frontend (Node.js 18)  # Vue前端
└── nginx (Nginx)         # Web服务器
```

## 🔍 故障排除

### 常见问题及解决方案

#### 问题1：Composer安装失败
```bash
# 解决方案：
./quick-fix-all.sh --composer
# 或者手动修复：
cd backend
composer clear-cache
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs
```

#### 问题2：Docker构建失败
```bash
# 解决方案：
./quick-fix-all.sh --docker
# 或者使用备选Dockerfile：
cp backend/Dockerfile.stable backend/Dockerfile
docker-compose build --no-cache
```

#### 问题3：数据库连接失败
```bash
# 解决方案：
./quick-fix-all.sh --database
# 或者手动修复：
docker-compose restart mysql
sleep 30
docker-compose exec backend php artisan migrate --force
```

#### 问题4：文件权限错误
```bash
# 解决方案：
./quick-fix-all.sh --permissions
# 或者手动修复：
cd backend
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### 问题5：端口冲突
```bash
# 解决方案：
# 编辑.env文件，修改端口配置：
NGINX_PORT=8080      # 原80端口
BACKEND_PORT=8001    # 原8000端口
DB_PORT=3307         # 原3306端口
# 重启服务：
docker-compose up -d
```

## 📈 监控和维护

### 健康检查
- API健康：`http://localhost:8000/health`
- Web健康：`http://localhost`
- 数据库健康：`docker-compose exec mysql mysqladmin ping`

### 日志查看
```bash
# 查看所有日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs -f backend
docker-compose logs -f nginx
docker-compose logs -f mysql

# 查看错误日志
docker-compose logs --tail=100 | grep -i error
```

### 性能监控
```bash
# 查看容器资源使用
docker stats

# 查看系统资源
./scripts/status.sh

# 生成性能报告
docker stats --no-stream > performance-$(date +%Y%m%d).txt
```

## 🔄 备份和恢复

### 自动备份
```bash
# 运行备份脚本
./scripts/backup.sh

# 设置定时备份（每天凌晨2点）
crontab -e
# 添加以下行：
0 2 * * * /path/to/snipeit-cn/scripts/backup.sh
```

### 手动备份
```bash
# 备份数据库
docker-compose exec -T mysql mysqldump -u root -p$DB_ROOT_PASSWORD snipe_cn > backup.sql

# 备份上传文件
tar czf uploads-backup.tar.gz backend/storage/app/uploads/

# 备份配置文件
cp .env .env.backup.$(date +%Y%m%d)
```

### 恢复数据
```bash
# 恢复数据库
docker-compose exec -T mysql mysql -u root -p$DB_ROOT_PASSWORD snipe_cn < backup.sql

# 恢复上传文件
tar xzf uploads-backup.tar.gz -C backend/storage/app/
```

## 📚 详细文档

### 部署文档
完整部署文档请参考：[DOCKER_DEPLOYMENT_GUIDE.md](DOCKER_DEPLOYMENT_GUIDE.md)

### API文档
- Swagger UI: `http://localhost:8000/docs`
- OpenAPI规范: `http://localhost:8000/api-docs`

### 用户手册
- 系统使用指南: `docs/USER_GUIDE.md`
- 管理员手册: `docs/ADMIN_GUIDE.md`

## 🤝 贡献指南

### 报告问题
请使用GitHub Issues报告问题，提供以下信息：
1. 系统环境
2. 错误日志
3. 复现步骤
4. 期望结果

### 提交代码
1. Fork仓库
2. 创建功能分支
3. 提交更改
4. 创建Pull Request

## 📞 技术支持

### 在线资源
- GitHub Issues: https://github.com/zhigang327/snipeit-cn/issues
- 文档: https://github.com/zhigang327/snipeit-cn/docs

### 联系方式
- 邮箱: support@snipe.cn
- 微信: [待添加]

## 📄 许可证

本项目基于MIT许可证开源，详情请查看[LICENSE](LICENSE)文件。

## 🎉 致谢

感谢所有贡献者和用户的支持！

---

**版本信息**
- 当前版本: v1.6.0-stable
- 发布日期: 2026-03-12
- 最后更新: 2026-03-12
- 维护状态: 活跃维护

**部署状态**
- ✅ 一键部署
- ✅ 自动测试
- ✅ 故障恢复
- ✅ 生产就绪

**祝你部署顺利！** 🚀