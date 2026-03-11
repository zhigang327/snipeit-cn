<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 创建角色
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => '管理员',
            'description' => '系统管理员,拥有所有权限'
        ]);

        $userRole = Role::create([
            'name' => 'user',
            'display_name' => '普通用户',
            'description' => '普通用户,拥有基本权限'
        ]);

        // 创建默认管理员
        $admin = User::create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'is_active' => true
        ]);

        // 分配管理员角色
        $admin->roles()->attach($adminRole->id);

        // 创建测试用户
        $testUser = User::create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'password' => Hash::make('123456'),
            'phone' => '13800138000',
            'employee_id' => 'EMP001',
            'position' => '软件工程师',
            'is_active' => true
        ]);

        $testUser->roles()->attach($userRole->id);

        // 创建根部门
        $rootDepartment = \App\Models\Department::create([
            'name' => '总公司',
            'code' => 'ROOT',
            'description' => '总公司',
            'parent_id' => null,
            'sort' => 0,
            'is_active' => true
        ]);

        // 创建二级部门
        $techDepartment = \App\Models\Department::create([
            'name' => '技术部',
            'code' => 'TECH',
            'description' => '技术研发部门',
            'parent_id' => $rootDepartment->id,
            'manager_id' => $testUser->id,
            'sort' => 1,
            'is_active' => true
        ]);

        $hrDepartment = \App\Models\Department::create([
            'name' => '人力资源部',
            'code' => 'HR',
            'description' => '人力资源部门',
            'parent_id' => $rootDepartment->id,
            'sort' => 2,
            'is_active' => true
        ]);

        // 创建三级部门
        $backendTeam = \App\Models\Department::create([
            'name' => '后端开发组',
            'code' => 'TECH-BE',
            'description' => '后端开发团队',
            'parent_id' => $techDepartment->id,
            'sort' => 1,
            'is_active' => true
        ]);

        $frontendTeam = \App\Models\Department::create([
            'name' => '前端开发组',
            'code' => 'TECH-FE',
            'description' => '前端开发团队',
            'parent_id' => $techDepartment->id,
            'sort' => 2,
            'is_active' => true
        ]);

        // 更新测试用户部门
        $testUser->update(['department_id' => $backendTeam->id]);

        // 创建资产分类
        $computerCategory = \App\Models\AssetCategory::create([
            'name' => '电脑设备',
            'code' => 'PC',
            'description' => '个人电脑和笔记本电脑',
            'parent_id' => null,
            'sort' => 1,
            'is_active' => true
        ]);

        $laptopCategory = \App\Models\AssetCategory::create([
            'name' => '笔记本',
            'code' => 'LAPTOP',
            'description' => '笔记本电脑',
            'parent_id' => $computerCategory->id,
            'sort' => 1,
            'is_active' => true
        ]);

        $desktopCategory = \App\Models\AssetCategory::create([
            'name' => '台式机',
            'code' => 'DESKTOP',
            'description' => '台式电脑',
            'parent_id' => $computerCategory->id,
            'sort' => 2,
            'is_active' => true
        ]);

        $monitorCategory = \App\Models\AssetCategory::create([
            'name' => '显示器',
            'code' => 'MONITOR',
            'description' => '电脑显示器',
            'sort' => 2,
            'is_active' => true
        ]);

        // 创建供应商
        $supplier = \App\Models\Supplier::create([
            'name' => '某科技有限公司',
            'code' => 'SUP001',
            'contact' => '王经理',
            'phone' => '010-12345678',
            'email' => 'wang@supplier.com',
            'address' => '北京市朝阳区',
            'is_active' => true
        ]);

        // 创建测试资产
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\Asset::create([
                'asset_tag' => sprintf('AST-%04d', $i),
                'name' => "Dell Latitude 5420 #{$i}",
                'description' => '办公笔记本电脑',
                'category_id' => $laptopCategory->id,
                'supplier_id' => $supplier->id,
                'purchase_price' => 8000.00,
                'purchase_date' => '2024-01-01',
                'brand' => 'Dell',
                'model' => 'Latitude 5420',
                'serial_number' => sprintf('DELL%08d', $i),
                'warranty_months' => 24,
                'department_id' => $techDepartment->id,
                'location' => 'A楼3层',
                'status' => $i <= 5 ? 'ready' : 'assigned',
                'created_by' => $admin->id,
            ]);
        }

        // 记录一些测试资产的历史
        $asset1 = \App\Models\Asset::where('asset_tag', 'AST-0001')->first();
        \App\Models\AssetHistory::create([
            'asset_id' => $asset1->id,
            'user_id' => $admin->id,
            'action' => 'create',
            'notes' => '创建资产',
        ]);

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin email: admin@example.com');
        $this->command->info('Admin password: admin123');
    }
}
