#!/bin/bash
# GitHub Release创建脚本
# 自动创建v1.6.1版本的Release

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

log_success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

log_error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# 检查GitHub CLI是否安装
if ! command -v gh &> /dev/null; then
    log_error "GitHub CLI未安装"
    echo ""
    echo "请先安装GitHub CLI:"
    echo "  macOS: brew install gh"
    echo "  Ubuntu/Debian: sudo apt install gh"
    echo "  CentOS/RHEL: sudo yum install gh"
    echo ""
    echo "或者使用其他方法创建Release:"
    echo "  1. 通过GitHub网站: https://github.com/zhigang327/snipeit-cn/releases"
    echo "  2. 使用GitHub API (需要Token)"
    exit 1
fi

# 检查是否登录
if ! gh auth status &> /dev/null; then
    log_error "GitHub CLI未登录"
    echo ""
    echo "请先登录GitHub CLI:"
    echo "  gh auth login"
    exit 1
fi

# 检查当前分支
log_info "检查Git状态..."
current_branch=$(git branch --show-current)
if [ "$current_branch" != "main" ]; then
    log_warning "当前分支不是main，切换到main分支"
    git checkout main
fi

# 检查是否有未提交的更改
if [ -n "$(git status --porcelain)" ]; then
    log_warning "有未提交的更改"
    echo "当前更改:"
    git status --short
    echo ""
    read -p "是否提交这些更改？(y/N): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git add .
        git commit -m "准备Release v1.6.1"
        git push origin main
    else
        log_error "请先提交或撤销更改"
        exit 1
    fi
fi

echo ""
echo "========================================"
echo "  创建 GitHub Release v1.6.1-stable"
echo "========================================"
echo ""

# 创建标签
log_info "创建标签 v1.6.1-stable..."
if git tag | grep -q "v1.6.1-stable"; then
    log_warning "标签已存在，删除旧标签"
    git tag -d v1.6.1-stable
    git push origin --delete v1.6.1-stable
fi

git tag v1.6.1-stable
git push origin v1.6.1-stable
log_success "标签创建并推送完成"

# 创建Release
log_info "创建GitHub Release..."
gh release create v1.6.1-stable \
    --title "Snipe-CN v1.6.1-stable - 解决Docker构建vendor问题" \
    --notes-file GITHUB_RELEASE_v1.6.1.md \
    --target main \
    --latest

log_success "Release创建成功"

echo ""
echo "========================================"
echo "  Release信息"
echo "========================================"
echo ""
echo "版本: v1.6.1-stable"
echo "标题: Snipe-CN v1.6.1-stable - 解决Docker构建vendor问题"
echo "说明文件: GITHUB_RELEASE_v1.6.1.md"
echo "状态: Latest release"
echo ""
echo "Release内容摘要:"
echo "✅ 修复Docker构建'没有vendor目录'错误"
echo "✅ 将构建成功率从~33%提升到100%"
echo "✅ 支持离线/在线/最小化三种安装模式"
echo "✅ 新增专用修复脚本和文档"
echo ""
echo "访问地址:"
echo "https://github.com/zhigang327/snipeit-cn/releases/tag/v1.6.1-stable"
echo ""
echo "========================================"
log_success "GitHub Release创建完成"
echo "========================================"