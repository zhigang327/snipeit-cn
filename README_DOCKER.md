# Snipe-CN 资产管理系统 (Docker版本)

![Docker](https://img.shields.io/badge/Docker-20.10%2B-blue)
![License](https://img.shields.io/badge/License-AGPL%203.0-green)
![Version](https://img.shields.io/badge/Version-v1.6.0-orange)
![Laravel](https://img.shields.io/badge/Laravel-10.x-red)
![Vue](https://img.shields.io/badge/Vue-3.x-green)

Snipe-CN是基于Snipe-IT汉化版的企业资产管理系统，提供完整的资产管理解决方案。本仓库提供Docker化的一键部署方案。

## 🚀 快速开始

### 1. 环境要求
- Docker 20.10+
- Docker Compose 2.0+
- 4GB+ 内存
- 20GB+ 磁盘空间

### 2. 一键部署（5分钟完成）
```bash
# 克隆项目
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 运行部署脚本
chmod +x deploy.sh
./deploy.sh
```

按照提示完成配置即可！

### 3. 访问系统
- **访问地址**: http://localhost
- **管理员账号**: `admin@example.com` / `admin123`

**首次登录后请立即修改密码！**

## 📦 功能特性

### ✅ 核心功能
- **资产管理** - 全生命周期管理（采购、登记、分配、转移、报废）
- **微信通知** - 实时推送变更、到期、盘点等通知
- **借用管理** - 完整的借用审批和归还流程
- **报废管理** - 报废申请、审批、处理流程
- **盘点管理** - 支持二维码扫描快速盘点
- **统计分析** - 多维度统计和报表功能

### ✅ 技术特性
- **现代化架构** - 前后端分离，RESTful API
- **Docker化部署** - 一键部署，易于维护
- **响应式设计** - 支持PC和移动端
- **完整汉化** - 中文界面，符合国内使用习惯
- **安全可靠** - 完善的权限控制和审计日志

## 🛠️ 系统架构

```
snipe-cn-system/
├── MySQL (3306)     - 数据存储
├── Redis (6379)     - 缓存和会话
├── Laravel (8000)   - 后端API服务
├── Vue.js (5173)    - 前端开发服务器
└── Nginx (80/443)   - Web服务器和反向代理
```

## ⚙️ 详细部署

### 标准部署流程
```bash
# 1. 克隆项目
git clone https://github.com/zhigang327/snipeit-cn.git
cd snipeit-cn

# 2. 配置环境
cp .env.example .env
# 编辑.env文件，至少修改数据库密码

# 3. 启动服务
docker-compose up -d

# 4. 初始化数据库
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force

# 5. 访问系统
# 打开浏览器访问: http://localhost
```

### 环境变量配置
最少需要修改的配置：
```bash
# 数据库密码（必须修改！）
DB_PASSWORD=YourStrongPassword123!
DB_ROOT_PASSWORD=RootStrongPassword456!

# 应用URL（根据实际访问地址修改）
APP_URL=http://your-domain.com

# 如果端口冲突，可以修改以下端口
NGINX_PORT=80      # Web访问端口
BACKEND_PORT=8000  # API端口
```

## 📊 技术栈

| 组件 | 技术 | 版本 |
|------|------|------|
| 后端框架 | Laravel | 10.x |
| 前端框架 | Vue.js | 3.x |
| UI框架 | Element Plus | 2.x |
| 数据库 | MySQL | 8.0 |
| 缓存 | Redis | 7.x |
| Web服务器 | Nginx | 最新版 |
| 容器化 | Docker | 20.10+ |
| 编排工具 | Docker Compose | 2.0+ |

## 🔧 系统管理

### 常用命令
```bash
# 启动服务
docker-compose up -d

# 停止服务
docker-compose down

# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f

# 进入容器
docker-compose exec backend bash

# 备份数据库
./deploy.sh  # 选择选项7
```

### 备份和恢复
```bash
# 备份数据库
docker-compose exec -T mysql mysqldump -u root -p${DB_ROOT_PASSWORD} snipe_cn > backup.sql

# 恢复数据库
docker-compose exec -T mysql mysql -u root -p${DB_ROOT_PASSWORD} snipe_cn < backup.sql
```

## 📈 性能指标

### 硬件推荐
| 场景 | CPU | 内存 | 存储 | 并发用户 |
|------|-----|------|------|----------|
| 小型团队 | 2核 | 4GB | 20GB | 10-50 |
| 中型企业 | 4核 | 8GB | 50GB | 50-200 |
| 大型企业 | 8核 | 16GB | 100GB+ | 200+ |

### 响应时间
- 页面加载: < 2秒
- API响应: < 200毫秒
- 数据库查询: < 100毫秒

## 🔒 安全配置

### 生产环境安全建议
1. **启用HTTPS** - 配置SSL证书
2. **修改默认密码** - 所有默认密码必须修改
3. **配置防火墙** - 限制不必要的端口访问
4. **定期备份** - 设置自动备份任务
5. **更新维护** - 定期更新系统和依赖

### 安全配置示例
```bash
# 启用HTTPS
APP_URL=https://your-domain.com
NGINX_PORT=443

# 配置防火墙（Ubuntu示例）
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

## ❓ 常见问题

### Q1: 端口冲突怎么办？
**A**: 修改`.env`文件中的端口配置，如：
```bash
NGINX_PORT=8080
BACKEND_PORT=8001
```

### Q2: 忘记管理员密码？
**A**: 使用以下命令重置：
```bash
docker-compose exec backend php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first()
>>> $user->password = bcrypt('new_password')
>>> $user->save()
>>> exit
```

### Q3: 如何导入现有资产数据？
**A**: 支持CSV导入：
1. 登录系统 → 资产管理 → 导入
2. 下载模板文件
3. 按模板格式填写数据
4. 上传导入

### Q4: 如何启用微信通知？
**A**: 配置企业微信机器人：
1. 在企业微信中创建机器人
2. 获取Webhook URL
3. 在`.env`中配置：
```bash
WECHAT_ENABLED=true
WECHAT_WEBHOOK_URL=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your_key
```

## 📚 文档目录

| 文档 | 说明 | 链接 |
|------|------|------|
| 部署指南 | 完整部署文档 | [DOCKER_DEPLOYMENT_GUIDE.md](DOCKER_DEPLOYMENT_GUIDE.md) |
| 快速部署 | 一键部署脚本 | [deploy.sh](deploy.sh) |
| 开发计划 | 功能开发路线图 | [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md) |
| 变更日志 | 版本更新记录 | [CHANGELOG.md](CHANGELOG.md) |

## 🆕 版本历史

### v1.6.0 (2026-03-12) - 当前版本
- ✅ 资产盘点管理功能
- ✅ 二维码扫描快速盘点
- ✅ 实时统计面板
- ✅ 批量审核和导出

### v1.5.0 (2026-03-12)
- ✅ 资产报废管理功能
- ✅ 多种报废类型支持
- ✅ 金额管理和损益分析
- ✅ 完整的审批流程

### v1.4.0 (2026-03-12)
- ✅ 资产借用管理功能
- ✅ 借用申请和审批流程
- ✅ 借用记录和统计分析
- ✅ 逾期提醒功能

### v1.3.0 (2026-03-12)
- ✅ 微信通知功能
- ✅ 多种通知类型
- ✅ 实时消息推送
- ✅ 通知配置管理

## 🤝 贡献指南

欢迎贡献代码！请遵循以下步骤：

1. Fork 本仓库
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📄 许可证

本项目基于 AGPL-3.0 许可证开源。详见 [LICENSE](LICENSE) 文件。

## 📞 技术支持

### 问题反馈
- **GitHub Issues**: [提交问题](https://github.com/zhigang327/snipeit-cn/issues)
- **邮箱支持**: zhigang327@gmail.com

### 社区支持
- **QQ群**: [加入交流群](#)
- **Discord**: [加入Discord](#)

### 商业支持
如需商业支持或定制开发，请联系：zhigang327@gmail.com

## 🌟 致谢

感谢以下开源项目：
- [Snipe-IT](https://snipeitapp.com/) - 原版资产管理系统
- [Laravel](https://laravel.com/) - PHP开发框架
- [Vue.js](https://vuejs.org/) - 前端框架
- [Element Plus](https://element-plus.org/) - UI组件库

---

**快速链接**:
- [✨ 立即部署](#快速开始)
- [📖 详细文档](DOCKER_DEPLOYMENT_GUIDE.md)
- [🐛 报告问题](https://github.com/zhigang327/snipeit-cn/issues)
- [⭐ 给个Star](https://github.com/zhigang327/snipeit-cn)

**祝您部署顺利，使用愉快！** 🎉