# Snipe-CN 项目概览

> 一个适合中国使用环境的IT资产管理系统,基于snipe-it改进,支持无限级部门层级

## 项目简介

Snipe-CN 是一个功能完善的开源IT资产管理系统,专为中国企业用户设计。系统基于 Laravel + Vue.js 技术栈开发,采用 Docker 容器化部署,开箱即用。

### 核心优势

- **🇨🇳 完全本地化**: 针对中国使用环境优化,支持中文界面、中国时区、人民币货币
- **🏢 多级部门**: 支持无限级部门层级,适应复杂的组织结构
- **🚀 开箱即用**: Docker一键部署,无需复杂配置
- **📊 数据洞察**: 丰富的统计图表和报表功能
- **🔍 全程追溯**: 完整的操作历史记录
- **🛡️ 安全可靠**: 基于角色的权限控制,数据加密存储

## 技术架构

### 系统架构

```
┌─────────────────────────────────────────────────────────────┐
│                         用户浏览器                            │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTP/HTTPS
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                       Nginx (Web服务器)                      │
│                   - 静态文件服务                              │
│                   - 反向代理                                  │
└─────────┬───────────────────────┬───────────────────────────┘
          │                       │
          ▼                       ▼
┌──────────────────┐    ┌──────────────────┐
│   Vue 3 前端     │    │  Laravel 后端    │
│   - Element Plus │    │  - REST API      │
│   - ECharts图表   │    │  - 业务逻辑      │
└──────────────────┘    └────────┬─────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
                    ▼             ▼             ▼
              ┌─────────┐  ┌─────────┐  ┌─────────┐
              │  MySQL  │  │  Redis  │  │ Storage │
              │  数据库  │  │  缓存   │  │  文件   │
              └─────────┘  └─────────┘  └─────────┘
```

### 技术栈详解

#### 后端 (Backend)
- **框架**: Laravel 10.48
- **语言**: PHP 8.2
- **认证**: Laravel Sanctum
- **权限**: Spatie Permission
- **图像处理**: Intervention Image
- **Excel**: Maatwebsite Excel
- **PDF**: Barryvdh DomPDF
- **二维码**: Simple QrCode

#### 前端 (Frontend)
- **框架**: Vue 3.4
- **构建工具**: Vite 5.0
- **UI组件库**: Element Plus 2.4
- **图表库**: ECharts 5.4
- **HTTP客户端**: Axios
- **状态管理**: Pinia
- **路由**: Vue Router 4.2

#### 基础设施 (Infrastructure)
- **容器化**: Docker & Docker Compose
- **Web服务器**: Nginx
- **数据库**: MySQL 8.0
- **缓存**: Redis 7.0

## 核心功能模块

### 1. 部门管理 🏢

**功能特点**:
- ✅ 无限级部门层级支持
- ✅ 树形结构可视化展示
- ✅ 部门负责人设置
- ✅ 部门统计(含子部门)
- ✅ 部门移动和重组
- ✅ 部门激活/禁用

**数据模型**:
```
Department
├── id: 部门ID
├── name: 部门名称
├── code: 部门编码(唯一)
├── parent_id: 父部门ID
├── manager_id: 负责人ID
├── sort: 排序
├── location: 地址
├── phone: 电话
├── is_active: 是否启用
└── relationships:
    ├── parent(): 父部门
    ├── children(): 子部门
    ├── ancestors(): 祖先部门
    └── descendants(): 后代部门
```

**API接口**:
- `GET /api/departments` - 获取部门列表
- `GET /api/departments/tree` - 获取部门树
- `GET /api/departments/{id}` - 获取部门详情
- `POST /api/departments` - 创建部门
- `PUT /api/departments/{id}` - 更新部门
- `DELETE /api/departments/{id}` - 删除部门
- `POST /api/departments/{id}/move` - 移动部门
- `GET /api/departments/{id}/statistics` - 部门统计

### 2. 资产管理 💼

**功能特点**:
- ✅ 资产录入和分类管理
- ✅ 资产分配和归还
- ✅ 资产状态追踪
- ✅ 资产二维码生成
- ✅ 保修期管理
- ✅ 资产搜索和筛选
- ✅ 操作历史记录

**资产生命周期**:
```
采购入库 → 在库 → 分配 → 已分配 → 归还 → 在库
                ↓
              维修 → 维修完成 → 在库
                ↓
              损坏 → 报废
                ↓
              丢失 → 报废
```

**数据模型**:
```
Asset
├── id: 资产ID
├── asset_tag: 资产标签(唯一)
├── name: 资产名称
├── category_id: 分类ID
├── supplier_id: 供应商ID
├── purchase_price: 采购价格
├── purchase_date: 采购日期
├── brand: 品牌
├── model: 型号
├── serial_number: 序列号
├── warranty_expiry: 保修到期日
├── department_id: 部门ID
├── user_id: 使用人ID
├── status: 状态
│   └── ready, assigned, maintenance, broken, lost, scrapped
└── relationships:
    ├── category(): 分类
    ├── supplier(): 供应商
    ├── department(): 部门
    ├── user(): 使用人
    └── histories(): 历史记录
```

**API接口**:
- `GET /api/assets` - 获取资产列表
- `GET /api/assets/{id}` - 获取资产详情
- `POST /api/assets` - 创建资产
- `PUT /api/assets/{id}` - 更新资产
- `DELETE /api/assets/{id}` - 删除资产
- `POST /api/assets/{id}/checkout` - 分配资产
- `POST /api/assets/{id}/checkin` - 归还资产
- `GET /api/assets/statistics` - 资产统计

### 3. 用户管理 👥

**功能特点**:
- ✅ 用户账号管理
- ✅ 角色权限分配
- ✅ 部门归属
- ✅ 员工信息管理
- ✅ 账号激活/禁用

**角色体系**:
- **管理员**: 拥有所有权限
- **普通用户**: 基本查看和操作权限

**数据模型**:
```
User
├── id: 用户ID
├── name: 姓名
├── email: 邮箱(唯一)
├── password: 密码
├── phone: 电话
├── department_id: 部门ID
├── employee_id: 工号
├── position: 职位
├── hire_date: 入职日期
├── is_active: 是否启用
└── relationships:
    ├── department(): 部门
    ├── roles(): 角色
    └── assets(): 名下资产
```

### 4. 数据统计 📊

**功能特点**:
- ✅ 资产状态分布图
- ✅ 资产价值统计
- ✅ 部门资产对比
- ✅ 实时数据更新

**统计维度**:
- 资产总数、已分配、在库、需处理
- 总资产价值、平均资产价值
- 部门用户数、资产数、资产价值
- 资产分类统计

## 项目结构

```
snipe/
├── backend/                    # Laravel 后端
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/     # 控制器
│   │   │   ├── Middleware/     # 中间件
│   │   │   └── Requests/       # 请求验证
│   │   ├── Models/             # 模型
│   │   └── Services/           # 服务层
│   ├── config/                 # 配置文件
│   ├── database/
│   │   ├── migrations/         # 数据库迁移
│   │   └── seeders/            # 数据填充
│   ├── public/                 # 公共目录
│   ├── resources/              # 前端资源
│   ├── routes/                 # 路由定义
│   ├── storage/                # 存储目录
│   ├── tests/                  # 测试文件
│   ├── composer.json           # PHP依赖
│   └── Dockerfile              # Docker镜像构建
│
├── frontend/                   # Vue 3 前端
│   ├── public/                 # 静态资源
│   ├── src/
│   │   ├── api/                # API接口
│   │   ├── components/         # 组件
│   │   ├── views/              # 页面
│   │   ├── router/             # 路由
│   │   ├── store/              # 状态管理
│   │   ├── utils/              # 工具函数
│   │   ├── App.vue             # 根组件
│   │   └── main.js             # 入口文件
│   ├── index.html              # HTML模板
│   ├── vite.config.js          # Vite配置
│   ├── package.json            # Node依赖
│   └── Dockerfile              # Docker镜像构建
│
├── docker/                     # Docker配置
│   ├── mysql/
│   │   └── my.cnf             # MySQL配置
│   ├── nginx/
│   │   ├── nginx.conf          # Nginx主配置
│   │   └── conf.d/            # Nginx站点配置
│   └── php/
│       └── php.ini             # PHP配置
│
├── .env.example                # 环境变量示例
├── .gitignore                  # Git忽略文件
├── docker-compose.yml          # Docker编排配置
├── package.json                # 项目依赖
├── README.md                   # 项目说明
├── DEPLOYMENT.md               # 部署文档
├── USER_MANUAL.md              # 用户手册
├── QUICK_START.md              # 快速入门
├── OVERVIEW.md                 # 项目概览(本文件)
├── CHANGELOG.md                # 更新日志
└── LICENSE                     # 许可证
```

## 部署架构

### Docker Compose 服务

```yaml
services:
  mysql:
    - MySQL 8.0 数据库
    - 数据持久化
    - 自定义配置

  redis:
    - Redis 7 缓存
    - 会话存储
    - 数据持久化

  backend:
    - Laravel 后端
    - PHP-FPM
    - 连接 MySQL & Redis
    - Artisan 服务

  frontend:
    - Vue 3 前端
    - Vite 开发服务器
    - 热重载

  nginx:
    - Nginx Web服务器
    - 反向代理
    - 静态文件服务
```

### 网络架构

```
Internet
    │
    ▼
┌─────────────────────────────┐
│       Nginx (Port 80/443)    │
└─────────────┬───────────────┘
              │
      ┌───────┴───────┐
      │               │
      ▼               ▼
┌─────────┐     ┌─────────┐
│ Frontend│     │ Backend │
│ :5173   │     │ :8000   │
└─────────┘     └────┬────┘
                     │
          ┌──────────┼──────────┐
          │          │          │
          ▼          ▼          ▼
    ┌────────┐  ┌────────┐  ┌────────┐
    │  MySQL │  │  Redis │  │Storage │
    │ :3306  │  │ :6379  │  │        │
    └────────┘  └────────┘  └────────┘
```

## 数据库设计

### 核心表结构

#### users (用户表)
```sql
- id: 主键
- name: 姓名
- email: 邮箱(唯一)
- phone: 电话
- password: 密码
- department_id: 部门ID
- employee_id: 工号
- position: 职位
- is_active: 是否启用
- timestamps: 时间戳
```

#### departments (部门表)
```sql
- id: 主键
- name: 部门名称
- code: 部门编码(唯一)
- parent_id: 父部门ID
- manager_id: 负责人ID
- sort: 排序
- is_active: 是否启用
- timestamps: 时间戳
```

#### assets (资产表)
```sql
- id: 主键
- asset_tag: 资产标签(唯一)
- name: 资产名称
- category_id: 分类ID
- supplier_id: 供应商ID
- purchase_price: 采购价格
- purchase_date: 采购日期
- brand: 品牌
- model: 型号
- serial_number: 序列号
- warranty_expiry: 保修到期日
- department_id: 部门ID
- user_id: 使用人ID
- status: 状态
- timestamps: 时间戳
```

#### asset_histories (资产历史表)
```sql
- id: 主键
- asset_id: 资产ID
- user_id: 操作人ID
- action: 操作类型
- old_values: 变更前值(JSON)
- new_values: 变更后值(JSON)
- timestamps: 时间戳
```

## 安全特性

### 认证与授权
- 🔐 JWT Token 认证
- 🔑 基于角色的权限控制(RBAC)
- 👥 用户会话管理
- 🚫 未授权访问拦截

### 数据安全
- 🔒 密码加密存储(BCrypt)
- 🗑️ 软删除机制
- 📝 操作日志记录
- 🛡️ SQL注入防护

### 网络安全
- 🌐 HTTPS 支持
- 🔗 CORS 配置
- 📋 请求验证
- 🚫 XSS 防护

## 性能优化

### 数据库优化
- ✅ 索引优化
- ✅ 查询优化
- ✅ 连接池配置
- ✅ 慢查询监控

### 缓存策略
- ✅ Redis 缓存
- ✅ 查询结果缓存
- ✅ 配置缓存
- ✅ 路由缓存

### 前端优化
- ✅ 代码分割
- ✅ 懒加载
- ✅ 图片优化
- ✅ Gzip 压缩

## 本地化适配

### 中文界面
- ✅ 全中文界面
- ✅ 中文字体优化
- ✅ 中文搜索支持

### 时区处理
- ✅ Asia/Shanghai 时区
- ✅ 日期本地化显示
- ✅ 时间格式适配

### 货币支持
- ✅ 人民币(CNY)货币单位
- ✅ 千分位分隔符
- ✅ 金额格式化

### 日期格式
- ✅ 日期: Y-m-d (2024-01-01)
- ✅ 时间: H:i:s (14:30:00)
- ✅ 中文星期显示

## 浏览器支持

| 浏览器 | 最低版本 | 状态 |
|--------|----------|------|
| Chrome | 90+ | ✅ 完全支持 |
| Firefox | 88+ | ✅ 完全支持 |
| Safari | 14+ | ✅ 完全支持 |
| Edge | 90+ | ✅ 完全支持 |
| IE 11 | - | ❌ 不支持 |

## 未来规划

### 短期计划 (1-3个月)
- [ ] Excel批量导入/导出
- [ ] 资产标签打印
- [ ] 邮件通知系统
- [ ] API文档完善

### 中期计划 (3-6个月)
- [ ] 移动端适配
- [ ] 资产折旧计算
- [ ] 维修工单系统
- [ ] 报表导出功能

### 长期计划 (6-12个月)
- [ ] 微信小程序
- [ ] 移动APP
- [ ] 数据分析平台
- [ ] 智能推荐功能

## 贡献指南

欢迎贡献代码、报告Bug、提出新功能建议!

### 开发环境搭建
```bash
# 1. 克隆项目
git clone <repository-url>
cd snipe

# 2. 配置环境
cp .env.example .env
# 编辑 .env 文件

# 3. 启动服务
docker-compose up -d

# 4. 初始化数据库
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed

# 5. 开始开发
# 前端: http://localhost:5173
# 后端: http://localhost:8000
```

### 代码规范
- PHP: 遵循 PSR-12 标准
- Vue: 遵循 Vue 3 官方风格指南
- 提交前运行 `npm run lint`

### 提交流程
1. Fork 项目
2. 创建特性分支
3. 提交代码
4. 推送到分支
5. 提交 Pull Request

## 许可证

本项目采用 MIT 许可证 - 详见 [LICENSE](LICENSE) 文件

## 联系方式

- 📧 邮箱: support@snipe.cn
- 📚 文档: https://docs.snipe.cn
- 💬 社区: (待建立)

## 致谢

本项目基于 [Snipe-IT](https://github.com/snipe/snipe-it) 进行本地化改进和功能增强。

感谢所有开源项目的贡献者!

---

**Snipe-CN - 让IT资产管理更简单!** 🎉
