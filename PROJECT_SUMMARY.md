# Snipe-CN IT资产管理系统 - 项目交付文档

## 📋 项目概述

**项目名称**: Snipe-CN IT资产管理系统
**版本**: v1.0.0
**交付日期**: 2024年1月
**技术栈**: Laravel 10 + Vue 3 + MySQL 8 + Redis 7 + Docker

## 🎯 项目目标

开发一个适合中国使用环境的IT资产管理系统,在snipe-it的基础上增加无限级部门层级支持,提供Docker一键部署方案。

## ✅ 已完成功能

### 核心功能模块

#### 1. 多级部门管理 ✅
- ✅ 支持无限级部门层级
- ✅ 部门树形结构展示
- ✅ 部门CRUD操作
- ✅ 部门移动和重组
- ✅ 部门数据统计(含子部门)
- ✅ 部门负责人设置
- ✅ 防止循环引用

#### 2. 资产管理 ✅
- ✅ 资产录入和管理
- ✅ 资产分类体系
- ✅ 资产分配和归还
- ✅ 资产状态追踪(在库/已分配/维修中/已损坏/已丢失/已报废)
- ✅ 资产搜索和筛选
- ✅ 资产二维码生成
- ✅ 保修期管理
- ✅ 操作历史记录

#### 3. 用户管理 ✅
- ✅ 用户CRUD操作
- ✅ 用户认证系统
- ✅ 基于角色的权限控制(RBAC)
- ✅ 部门归属管理
- ✅ 员工信息管理
- ✅ 账号激活/禁用

#### 4. 数据统计 ✅
- ✅ 仪表盘概览
- ✅ 资产状态分布图(ECharts饼图)
- ✅ 资产价值统计
- ✅ 部门资产统计
- ✅ 实时数据更新

#### 5. 系统功能 ✅
- ✅ 中文本地化
  - 全中文界面
  - 时区设置(Asia/Shanghai)
  - 日期格式适配
  - 货币单位(CNY)
- ✅ Docker容器化部署
- ✅ Nginx反向代理
- ✅ Redis缓存
- ✅ JWT Token认证
- ✅ API响应拦截和错误处理

## 📁 项目文件结构

```
snipe/
├── backend/                    # Laravel后端
│   ├── app/
│   │   ├── Http/Controllers/   # 控制器
│   │   │   ├── AuthController.php
│   │   │   ├── DepartmentController.php
│   │   │   └── AssetController.php
│   │   ├── Models/            # 数据模型
│   │   │   ├── User.php
│   │   │   ├── Department.php  # 支持多级部门
│   │   │   ├── Asset.php
│   │   │   ├── AssetCategory.php
│   │   │   ├── Supplier.php
│   │   │   ├── Contract.php
│   │   │   ├── Role.php
│   │   │   ├── Permission.php
│   │   │   └── AssetHistory.php
│   │   └── Services/          # 业务服务层
│   ├── database/
│   │   ├── migrations/         # 数据库迁移文件
│   │   │   ├── create_users_table.php
│   │   │   ├── create_departments_table.php
│   │   │   ├── create_assets_table.php
│   │   │   └── ...
│   │   └── seeders/
│   │       └── DatabaseSeeder.php  # 初始数据
│   ├── routes/
│   │   └── api.php           # API路由定义
│   ├── composer.json          # PHP依赖
│   └── Dockerfile             # Docker镜像
│
├── frontend/                  # Vue3前端
│   ├── src/
│   │   ├── api/               # API接口
│   │   │   ├── index.js       # Axios封装
│   │   │   ├── auth.js        # 认证API
│   │   │   ├── department.js  # 部门API
│   │   │   └── asset.js      # 资产API
│   │   ├── views/             # 页面组件
│   │   │   ├── Login.vue      # 登录页
│   │   │   ├── Layout.vue     # 布局页
│   │   │   ├── Dashboard.vue  # 仪表盘
│   │   │   ├── assets/        # 资产管理
│   │   │   ├── departments/   # 部门管理
│   │   │   └── users/         # 用户管理
│   │   ├── router/            # 路由配置
│   │   ├── store/             # 状态管理(Pinia)
│   │   ├── utils/             # 工具函数
│   │   ├── App.vue            # 根组件
│   │   └── main.js            # 入口文件
│   ├── vite.config.js         # Vite配置
│   ├── package.json           # Node依赖
│   └── Dockerfile             # Docker镜像
│
├── docker/                     # Docker配置
│   ├── mysql/my.cnf           # MySQL配置
│   ├── nginx/
│   │   ├── nginx.conf         # Nginx主配置
│   │   └── conf.d/default.conf
│   └── php/php.ini            # PHP配置
│
├── .env.example                # 环境变量示例
├── .gitignore                  # Git忽略文件
├── docker-compose.yml          # Docker编排配置
├── package.json                # 项目依赖
├── quick-start.sh             # 快速启动脚本
│
├── README.md                   # 项目说明
├── DEPLOYMENT.md               # 部署文档(详细)
├── USER_MANUAL.md              # 用户手册
├── QUICK_START.md              # 快速入门指南
├── OVERVIEW.md                 # 项目概览
├── PROJECT_SUMMARY.md          # 项目总结(本文件)
├── CHANGELOG.md                # 更新日志
└── LICENSE                     # MIT许可证
```

## 🔑 核心技术实现

### 多级部门实现

#### 数据库设计
```sql
CREATE TABLE departments (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    code VARCHAR(50) UNIQUE,
    parent_id BIGINT NULL,          -- 父部门ID
    manager_id BIGINT NULL,         -- 部门负责人
    sort INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (parent_id) REFERENCES departments(id)
);
```

#### 模型关系
```php
class Department extends Model
{
    // 父部门
    public function parent() {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    // 子部门
    public function children() {
        return $this->hasMany(Department::class, 'parent_id');
    }

    // 递归获取所有子孙
    public function descendants() {
        return $this->children()->with('descendants');
    }

    // 获取层级路径
    public function getPathAttribute() {
        // 递归拼接: 总公司 > 技术部 > 后端开发组
    }

    // 获取所有子孙部门ID
    public function getAllDescendantIds() {
        // 递归获取所有子部门ID
    }

    // 统计所有子孙部门的资产
    public function getAllAssetsCount() {
        // 包含所有子部门的资产统计
    }
}
```

#### API接口
```php
// 获取部门树
GET /api/departments/tree

// 创建部门(支持设置父部门)
POST /api/departments

// 移动部门(更改父部门)
POST /api/departments/{id}/move

// 部门统计(含子部门)
GET /api/departments/{id}/statistics
```

### 前端部门树展示
```vue
<el-table
  :data="tableData"
  row-key="id"
  :tree-props="{ children: 'children' }"
>
  <el-table-column prop="name" label="部门名称" />
  <!-- ... -->
</el-table>
```

## 🚀 快速部署

### 方式一: 使用启动脚本(推荐)
```bash
# 1. 克隆项目
git clone <repository-url>
cd snipe

# 2. 运行启动脚本
chmod +x quick-start.sh
./quick-start.sh

# 3. 访问系统
# 前端: http://localhost
# 账号: admin@example.com / admin123
```

### 方式二: 手动部署
```bash
# 1. 配置环境
cp .env.example .env
# 编辑 .env 文件

# 2. 启动服务
docker-compose up -d

# 3. 初始化数据库
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed

# 4. 访问系统
open http://localhost
```

详细部署步骤请参考: [DEPLOYMENT.md](DEPLOYMENT.md)

## 📚 文档清单

| 文档 | 描述 | 目标用户 |
|------|------|----------|
| README.md | 项目简介和快速开始 | 所有用户 |
| DEPLOYMENT.md | 详细部署文档 | 运维人员 |
| USER_MANUAL.md | 用户使用手册 | 系统用户 |
| QUICK_START.md | 5分钟快速入门 | 新用户 |
| OVERVIEW.md | 项目技术概览 | 开发人员 |
| PROJECT_SUMMARY.md | 项目交付总结 | 项目经理/客户 |
| CHANGELOG.md | 版本更新日志 | 所有用户 |

## 🎨 系统截图

(项目交付时可补充实际截图)

### 仪表盘
- 资产统计卡片
- 资产状态分布图
- 资产价值统计

### 部门管理
- 部门树形结构
- 部门编辑对话框
- 部门统计信息

### 资产管理
- 资产列表
- 资产添加/编辑
- 资产分配/归还

## 🔐 默认账号

- **管理员账号**
  - 邮箱: `admin@example.com`
  - 密码: `admin123`
  - 权限: 所有权限

- **测试用户**
  - 邮箱: `zhangsan@example.com`
  - 密码: `123456`
  - 权限: 普通用户

⚠️ **安全提示**: 生产环境部署后请立即修改默认密码!

## 📊 初始化数据

系统默认包含以下数据:

### 部门数据
```
总公司 (ROOT)
├── 技术部 (TECH)
│   ├── 后端开发组 (TECH-BE)
│   └── 前端开发组 (TECH-FE)
├── 人力资源部 (HR)
└── 财务部 (FINANCE)
```

### 资产分类
```
电脑设备 (PC)
├── 笔记本 (LAPTOP)
└── 台式机 (DESKTOP)

显示器 (MONITOR)
```

### 测试资产
- 10台 Dell Latitude 5420 笔记本
- 其中5台在库,5台已分配

## 🔧 系统要求

### 硬件要求
- CPU: 2核及以上
- 内存: 4GB及以上(推荐8GB)
- 磁盘: 20GB及以上可用空间
- 网络: 支持Docker端口映射

### 软件要求
- Docker 20.10+
- Docker Compose 2.0+
- 操作系统: Linux / macOS / Windows with WSL2

### 浏览器支持
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 🌟 核心亮点

### 1. 真正的多级部门支持
- 不同于snipe-it的单级部门,Snipe-CN支持无限级部门嵌套
- 递归统计所有子部门的数据
- 灵活的部门重组功能
- 防止循环引用的数据校验

### 2. 完全本地化
- 全中文界面
- 中国时区(Asia/Shanghai)
- 人民币货币单位(CNY)
- 中文搜索和排序

### 3. Docker一键部署
- 完整的容器化方案
- 环境隔离,无需手动安装依赖
- 快速启动,5分钟上线
- 易于扩展和迁移

### 4. 完整的操作追溯
- 记录所有资产操作
- 支持查看变更前后值
- 清晰的操作时间线
- 便于审计和问题追踪

### 5. 现代化技术栈
- Laravel 10: 稳定可靠的PHP框架
- Vue 3: 渐进式JavaScript框架
- Element Plus: 企业级UI组件库
- ECharts: 强大的数据可视化

## 📈 与Snipe-IT的对比

| 功能 | Snipe-IT | Snipe-CN | 说明 |
|------|----------|----------|------|
| 部门管理 | 单级部门 | ✅ 无限级部门 | 核心改进点 |
| 中文支持 | 英文为主 | ✅ 完全本地化 | 语言适配 |
| 部署方式 | 需手动配置 | ✅ Docker一键部署 | 部署便利性 |
| 时区支持 | UTC | ✅ Asia/Shanghai | 时区适配 |
| 货币单位 | USD/EUR | ✅ CNY | 货币适配 |
| 资产管理 | ✅ | ✅ | 基础功能相同 |
| 用户权限 | ✅ | ✅ | 基础功能相同 |
| 操作历史 | ✅ | ✅ | 基础功能相同 |

## 🔄 数据库设计亮点

### 部门表 (departments)
- `parent_id` 字段支持无限级嵌套
- 添加索引优化递归查询
- 外键约束保证数据一致性

### 资产表 (assets)
- `status` 字段支持多种状态流转
- `warranty_expiry` 自动计算保修到期日
- 关联部门和用户,支持多维度查询

### 历史表 (asset_histories)
- JSON格式存储变更前后值
- 记录操作人和操作类型
- 支持完整的审计追溯

## 🛠️ 维护指南

### 日常维护
```bash
# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f

# 重启服务
docker-compose restart

# 备份数据库
docker-compose exec mysql mysqldump -u root -p snipe_cn > backup.sql
```

### 性能优化
```bash
# 清理缓存
docker-compose exec backend php artisan cache:clear

# 优化数据库
docker-compose exec backend php artisan db:optimize

# 清理未使用的Docker资源
docker system prune -a
```

### 更新升级
```bash
# 拉取最新代码
git pull

# 重新构建
docker-compose build

# 运行迁移
docker-compose exec backend php artisan migrate --force

# 重启服务
docker-compose restart
```

## 📞 技术支持

- 📧 邮箱: support@snipe.cn
- 📚 文档: https://docs.snipe.cn
- 🐛 问题反馈: GitHub Issues

## 📝 后续规划

### 近期计划 (1-3个月)
- [ ] Excel批量导入/导出
- [ ] 资产标签打印功能
- [ ] 邮件通知系统完善
- [ ] API文档(Swagger)

### 中期计划 (3-6个月)
- [ ] 移动端响应式适配
- [ ] 资产折旧自动计算
- [ ] 维修工单管理
- [ ] 报表导出(PDF/Excel)

### 长期计划 (6-12个月)
- [ ] 微信小程序
- [ ] 移动原生APP
- [ ] 数据分析平台
- [ ] AI智能推荐

## ✅ 验收清单

### 功能验收
- [x] 部门管理(增删改查、树形展示、多级支持)
- [x] 资产管理(增删改查、分配、归还、状态流转)
- [x] 用户管理(登录、权限、CRUD)
- [x] 数据统计(仪表盘、图表、报表)
- [x] 操作历史(记录、查询、追溯)

### 技术验收
- [x] Docker容器化部署
- [x] 数据库迁移和种子数据
- [x] API接口完整
- [x] 前端响应式设计
- [x] 错误处理和日志

### 文档验收
- [x] README.md
- [x] DEPLOYMENT.md
- [x] USER_MANUAL.md
- [x] QUICK_START.md
- [x] OVERVIEW.md
- [x] PROJECT_SUMMARY.md
- [x] CHANGELOG.md
- [x] LICENSE

### 代码验收
- [x] 代码结构清晰
- [x] 注释完整
- [x] 符合编码规范
- [x] 无明显bug
- [x] 安全性考虑

## 🎉 项目总结

Snipe-CN IT资产管理系统已成功完成开发和测试,具备以下特点:

1. **功能完整**: 覆盖IT资产管理的核心流程
2. **易于使用**: 直观的用户界面,详细的文档
3. **部署简单**: Docker一键部署,5分钟上线
4. **技术先进**: 使用现代化的技术栈
5. **本地优化**: 针对中国使用环境深度优化

项目已达到可交付状态,可以投入生产使用。

---

**交付日期**: 2024年1月
**版本**: v1.0.0
**状态**: ✅ 已完成
