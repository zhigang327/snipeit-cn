<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryTasksTable extends Migration
{
    /**
     * 运行数据库迁移
     */
    public function up()
    {
        Schema::create('inventory_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number')->unique()->comment('任务编号');
            $table->string('task_name')->comment('任务名称');
            $table->enum('task_type', ['periodic', 'random', 'full', 'spot', 'cycle'])->comment('任务类型');
            $table->text('description')->nullable()->comment('任务描述');
            
            // 计划信息
            $table->date('start_date')->comment('开始日期');
            $table->date('end_date')->comment('结束日期');
            $table->timestamp('scheduled_start')->nullable()->comment('计划开始时间');
            $table->timestamp('scheduled_end')->nullable()->comment('计划结束时间');
            
            // 范围筛选
            $table->json('department_ids')->nullable()->comment('部门范围');
            $table->json('category_ids')->nullable()->comment('类别范围');
            $table->json('location_filters')->nullable()->comment('位置筛选');
            $table->integer('asset_count')->nullable()->comment('资产数量');
            
            // 负责人和参与者
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->json('participant_ids')->nullable()->comment('参与者ID列表');
            
            // 状态和进度
            $table->enum('status', ['draft', 'active', 'in_progress', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->integer('total_assets')->default(0)->comment('总资产数');
            $table->integer('completed_assets')->default(0)->comment('已完成资产数');
            $table->integer('found_assets')->default(0)->comment('已找到资产数');
            $table->integer('not_found_assets')->default(0)->comment('未找到资产数');
            $table->integer('mismatched_assets')->default(0)->comment('不匹配资产数');
            $table->integer('flagged_assets')->default(0)->comment('标记资产数');
            
            // 进度计算
            $table->decimal('completion_rate', 5, 2)->default(0)->comment('完成率');
            $table->decimal('accuracy_rate', 5, 2)->nullable()->comment('准确率');
            
            // 通知设置
            $table->boolean('notify_on_start')->default(true)->comment('开始时通知');
            $table->boolean('notify_on_complete')->default(true)->comment('完成时通知');
            $table->boolean('notify_on_issues')->default(true)->comment('有异常时通知');
            
            // 重复设置
            $table->enum('repeat_type', ['none', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('none');
            $table->integer('repeat_interval')->default(1)->comment('重复间隔');
            $table->json('repeat_days')->nullable()->comment('重复星期');
            $table->date('repeat_until')->nullable()->comment('重复截止日期');
            $table->boolean('create_next_on_complete')->default(false)->comment('完成后创建下次任务');
            
            // 设置选项
            $table->boolean('require_photos')->default(false)->comment('要求拍照');
            $table->boolean('require_gps')->default(false)->comment('要求GPS定位');
            $table->boolean('require_condition_check')->default(true)->comment('要求状况检查');
            $table->boolean('allow_qr_scan')->default(true)->comment('允许二维码扫描');
            
            // 自动处理规则
            $table->boolean('auto_update_location')->default(false)->comment('自动更新位置');
            $table->boolean('auto_update_status')->default(false)->comment('自动更新状态');
            $table->boolean('auto_assign_missing')->default(false)->comment('自动分配缺失');
            
            // 审核设置
            $table->boolean('require_review')->default(false)->comment('需要审核');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->onDelete('set null');
            
            // 完成信息
            $table->timestamp('started_at')->nullable()->comment('实际开始时间');
            $table->timestamp('completed_at')->nullable()->comment('实际完成时间');
            $table->text('completion_notes')->nullable()->comment('完成备注');
            
            $table->timestamps();
            $table->softDeletes();
            
            // 索引优化
            $table->index('task_number');
            $table->index('task_type');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('assigned_to');
            $table->index('completed_at');
        });
    }

    /**
     * 回滚数据库迁移
     */
    public function down()
    {
        Schema::dropIfExists('inventory_tasks');
    }
}