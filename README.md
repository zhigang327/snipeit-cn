# Snipe-CN IT资产管理系统

一个适合中国使用环境的IT资产管理系统,基于snipe-it改进,支持无限级部门层级。

## 主要特性

- 🏢 **多级部门管理**: 支持无限级部门树形结构
- 💼 **全生命周期管理**: 资产采购、入库、领用、归还、盘点、报废
- 👥 **用户权限管理**: 细粒度的角色和权限控制
- 🔍 **强大的搜索**: 支持多条件筛选和全文搜索
- 📊 **数据统计**: 丰富的报表和数据可视化
- 🔔 **微信通知**: 企业微信机器人实时通知(到期提醒、资产变动、盘点通知等)
- 🌏 **中文优化**: 时区、日期格式、货币等本地化适配
- 🐳 **Docker部署**: 一键部署,开箱即用
- 📱 **扫码盘点**: 支持二维码生成和PDA扫码盘点
- 🏷️ **标签打印**: 支持资产二维码标签打印

## 技术栈

- **后端**: Laravel 10 + PHP 8.2
- **前端**: Vue 3 + TypeScript + Vite + Element Plus
- **数据库**: MySQL 8.0
- **缓存**: Redis 7
- **Web服务器**: Nginx

## 快速开始

### 前置要求

- Docker & Docker Compose
- 8GB+ 内存
- 20GB+ 可用磁盘空间

### 安装步骤

1. 克隆项目
```bash
git clone <repository-url>
cd snipe
```

2. 配置环境变量
```bash
cp .env.example .env
# 根据需要修改.env文件中的配置
```

3. 构建并启动服务
```bash
docker-compose up -d
```

4. 初始化数据库
```bash
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed
```

5. 访问系统
- 前端界面: http://localhost
- 默认管理员账号: admin@example.com / admin123

### 开发模式

```bash
npm install
npm run dev
```

### 生产部署

```bash
npm run build
docker-compose up -d
```

## 目录结构

```
snipe/
├── backend/          # Laravel后端
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── routes/
│   └── public/
├── frontend/         # Vue3前端
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── views/
│   │   └── store/
│   └── public/
├── docker/           # Docker配置
│   ├── mysql/
│   ├── nginx/
│   └── php/
├── docker-compose.yml
└── README.md
```

## 主要模块

### 1. 多级部门管理
- 支持无限级部门树形结构
- 部门负责人设置
- 部门资产统计
- 部门权限继承

### 2. 资产管理
- 资产分类管理
- 资产采购入库
- 资产分配领用
- 资产归还
- 资产盘点
- 资产报废处理
- 二维码生成和打印
- 扫码查询资产信息

### 3. 扫码盘点
- PDA扫码盘点支持
- 摄像头扫码
- 手动输入资产标签
- 条码枪支持
- 盘点进度跟踪
- 异常资产标记
- 盘点报告生成

### 4. 微信通知
- 企业微信机器人集成
- 资产到期提醒
- 资产变动通知
- 盘点任务通知
- 通知开关配置
- Web界面测试

### 5. 用户管理
- 用户账号管理
- 角色权限分配
- 部门归属
- 操作日志

### 6. 合同管理
- 供应商管理
- 采购合同
- 维护合同
- 合同到期提醒

### 7. 报表统计
- 资产分布统计
- 资产价值统计
- 部门资产对比
- 导出Excel/PDF

## 系统截图

[待补充]

## 常见问题

### 如何修改默认密码?
```bash
docker-compose exec backend php artisan tinker
>>> \App\Models\User::where('email', 'admin@example.com')->update(['password' => bcrypt('new_password')])
```

### 如何备份数据库?
```bash
docker-compose exec mysql mysqldump -u root -p snipe_cn > backup.sql
```

### 如何恢复数据库?
```bash
docker-compose exec -T mysql mysql -u root -p snipe_cn < backup.sql
```

### 如何配置微信通知?
参考 [微信通知配置指南](WECHAT_NOTIFICATION.md)

## 开发计划

- [ ] 移动端适配
- [x] 微信通知集成
- [x] 条码/二维码打印
- [x] 资产折旧计算
- [ ] 资产维修记录
- [ ] API接口文档(Swagger)
- [ ] Excel批量导入导出
- [ ] 报表导出(PDF/Excel)

## 许可证

MIT License

## 致谢

本系统基于 [Snipe-IT](https://github.com/snipe/snipe-it) 进行本地化改进和功能增强。
