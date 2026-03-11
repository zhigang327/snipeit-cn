# GitHub 发布指南

## 发布步骤

### 1. 创建GitHub仓库

1. 登录 https://github.com
2. 点击右上角 "+" → "New repository"
3. 填写仓库信息:
   - Repository name: `snipe-cn`
   - Description: `适合中国使用环境的IT资产管理系统,支持无限级部门和PDA扫码盘点`
   - Public/Private: 选择 Public
   - Initialize with README: 不勾选(我们已经有了)
4. 点击 "Create repository"

### 2. 推送代码到GitHub

```bash
# 进入项目目录
cd /Volumes/970/snipe

# 配置远程仓库(替换YOUR_USERNAME为你的GitHub用户名)
git remote add origin https://github.com/YOUR_USERNAME/snipe-cn.git

# 推送代码
git branch -M main
git push -u origin main
```

### 3. 创建GitHub Release

#### 方式一: 通过网页界面
1. 进入GitHub仓库
2. 点击右侧 "Releases" → "Create a new release"
3. 填写Release信息:

```
Tag: v1.0.0
Release title: 🎉 Snipe-CN v1.0.0 - 首个正式版本

Description:

## 主要功能

### 🏢 多级部门管理
- 支持无限级部门树形结构
- 部门负责人设置
- 部门数据统计(含子部门)
- 灵活的部门重组

### 💼 资产管理
- 资产全生命周期管理
- 资产分配和归还
- 资产状态追踪
- 操作历史记录

### 📱 扫码盘点
- PDA扫码盘点支持
- 二维码生成和打印
- 实时盘点进度跟踪
- 异常资产标记

### 🌏 完全本地化
- 全中文界面
- 中国时区(Asia/Shanghai)
- 人民币货币单位(CNY)
- 日期格式适配

### 🐳 Docker一键部署
- 完整的容器化方案
- 开箱即用
- 5分钟快速上线

## 快速开始

```bash
git clone https://github.com/YOUR_USERNAME/snipe-cn.git
cd snipe-cn
cp .env.example .env
docker-compose up -d
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed
```

访问 http://localhost
默认账号: admin@example.com / admin123

## 文档

- [部署文档](DEPLOYMENT.md)
- [用户手册](USER_MANUAL.md)
- [快速入门](QUICK_START.md)
- [二维码指南](QR_CODE_GUIDE.md)
- [项目概览](OVERVIEW.md)

## 更新日志

详见 [CHANGELOG.md](CHANGELOG.md)

## 致谢

本项目基于 [Snipe-IT](https://github.com/snipe/snipe-it) 进行本地化改进和功能增强。

⚠️ 首次登录后请立即修改默认密码!
```

4. 点击 "Publish release"

#### 方式二: 使用GitHub CLI
```bash
# 安装GitHub CLI
# macOS
brew install gh

# 登录
gh auth login

# 创建Release
gh release create v1.0.0 \
  --title "🎉 Snipe-CN v1.0.0 - 首个正式版本" \
  --notes-file GITHUB_RELEASE_NOTES.md
```

### 4. 添加主题标签(可选)

1. 进入仓库设置 "Settings"
2. 找到 "Repository topics"
3. 添加相关标签:
   - `asset-management`
   - `it-asset`
   - `laravel`
   - `vue3`
   - `docker`
   - `inventory`
   - `qr-code`
   - `pda`
   - `multi-level-department`

### 5. 设置仓库信息

在仓库设置中完善:
- Description: 清晰的项目描述
- Website: 项目官网(如有)
- Topics: 相关标签
- License: MIT
- Labels: Issue标签(如bug、feature等)

### 6. 创建GitHub Pages(可选)

如果需要在线文档:

1. 进入仓库设置 "Pages"
2. Source: 选择 `docs` 分支或 `main` 分支的 `/docs` 目录
3. 点击 Save
4. 访问 https://YOUR_USERNAME.github.io/snipe-cn/

## 后续版本发布

### 修改版本号

1. 更新 `CHANGELOG.md`
2. 更新 `README.md` 中的版本信息
3. 更新 `backend/composer.json` 版本
4. 更新 `frontend/package.json` 版本

### 提交更新

```bash
git add .
git commit -m "Release: v1.0.1"
git push
```

### 创建Release

```bash
gh release create v1.0.1 \
  --title "v1.0.1 - Bug修复" \
  --notes "修复若干已知问题..."
```

## GitHub Actions CI/CD(可选)

可以添加自动化流程:

### .github/workflows/ci.yml

```yaml
name: CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'

    - name: Copy .env
      run: php -r "file_exists('.env') || copy('.env.example', '.env');"

    - name: Install Dependencies
      working-directory: ./backend
      run: composer install -q --no-ansi --no-interaction --no-scripts

    - name: Run Tests
      working-directory: ./backend
      run: php artisan test
```

## 推广建议

1. **中文技术社区**:
   - 掘金
   - 知乎专栏
   - 博客园

2. **社交媒体**:
   - 微博
   - 微信公众号

3. **技术论坛**:
   - V2EX
   - SegmentFault
   - GitHub Trending

4. **邮件通知**:
   - 发送给潜在用户
   - 发布到相关邮件列表

## 常见问题

### Q: Release创建失败?
A: 检查Tag是否已存在,避免重复Tag。

### Q: 推送失败?
A: 检查远程仓库URL是否正确,确认有写入权限。

### Q: 如何删除错误的Release?
A: 进入Releases页面,点击Delete按钮。

## 相关链接

- [GitHub文档: Creating releases](https://docs.github.com/en/repositories/releasing-projects-on-github/managing-releases-in-a-repository)
- [Semantic Versioning](https://semver.org/)
- [Keep a Changelog](https://keepachangelog.com/)
