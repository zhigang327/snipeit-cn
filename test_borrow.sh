#!/bin/bash

echo "========================================"
echo "资产借用管理功能测试脚本"
echo "========================================"
echo ""

echo "1. 检查后端文件..."
files=(
    "backend/app/Models/BorrowRecord.php"
    "backend/app/Services/BorrowService.php"
    "backend/app/Http/Controllers/BorrowController.php"
    "backend/database/migrations/2024_01_01_000013_create_borrow_records_table.php"
)

all_exist=true
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (缺失)"
        all_exist=false
    fi
done
echo ""

echo "2. 检查前端文件..."
frontend_files=(
    "frontend/src/views/borrow/Index.vue"
    "frontend/src/api/borrow.js"
)

for file in "${frontend_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (缺失)"
        all_exist=false
    fi
done
echo ""

echo "3. 检查API路由配置..."
if grep -q "borrow" backend/routes/api.php; then
    echo "  ✓ API路由已配置"
else
    echo "  ✗ API路由未配置"
    all_exist=false
fi
echo ""

echo "4. 检查前端路由配置..."
if grep -q "borrow" frontend/src/router/index.js; then
    echo "  ✓ 前端路由已配置"
else
    echo "  ✗ 前端路由未配置"
    all_exist=false
fi
echo ""

echo "5. 检查菜单配置..."
if grep -q "borrow" frontend/src/views/Layout.vue; then
    echo "  ✓ 菜单已配置"
else
    echo "  ✗ 菜单未配置"
    all_exist=false
fi
echo ""

echo "6. 检查微信通知配置..."
if grep -q "borrow" backend/config/services.php; then
    echo "  ✓ 微信通知配置已更新"
else
    echo "  ✗ 微信通知配置未更新"
    all_exist=false
fi
echo ""

echo "7. 代码行数统计..."
echo "  后端:"
echo "    - BorrowRecord.php: $(wc -l < backend/app/Models/BorrowRecord.php) 行"
echo "    - BorrowService.php: $(wc -l < backend/app/Services/BorrowService.php) 行"
echo "    - BorrowController.php: $(wc -l < backend/app/Http/Controllers/BorrowController.php) 行"
echo "  前端:"
echo "    - Index.vue: $(wc -l < frontend/src/views/borrow/Index.vue) 行"
echo "    - borrow.js: $(wc -l < frontend/src/api/borrow.js) 行"
echo ""

echo "8. 检查API端点..."
endpoints=(
    "api/borrow"
    "borrow/{borrow}/approve"
    "borrow/{borrow}/reject"
    "borrow/{borrow}/confirm-borrow"
    "borrow/{borrow}/return"
    "borrow/{borrow}/cancel"
    "borrow/statistics"
    "borrow/overdue"
    "assets/{asset}/borrow/history"
    "borrow/export"
)

for endpoint in "${endpoints[@]}"; do
    if grep -q "$endpoint" backend/routes/api.php; then
        echo "  ✓ /$endpoint"
    else
        echo "  ✗ /$endpoint (缺失)"
        all_exist=false
    fi
done
echo ""

echo "========================================"
if [ "$all_exist" = true ]; then
    echo "测试结果: ✅ 全部通过"
else
    echo "测试结果: ❌ 存在问题"
fi
echo "========================================"
