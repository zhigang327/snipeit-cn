<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryRecordsTable extends Migration
{
    /**
     * 运行数据库迁移
     */
    public function up()
    {
        Schema::create('inventory_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_task_id')->nullable()->constrained()->onDelete('set null');
            $table->string('inventory_number')->unique()->comment('盘点编号');
            $table->date('inventory_date')->comment('盘点日期');
            $table->enum('inventory_type', ['periodic', 'random', 'full', 'spot', 'cycle'])->comment('盘点类型');
            
            // 盘点结果
            $table->enum('physical_status', ['found', 'not_found', 'damaged', 'scrapped', 'transferred'])->comment('实物状态');
            $table->enum('status_match', ['matched', 'location_mismatch', 'user_mismatch', 'both_mismatch'])->comment('状态匹配');
            
            // 位置信息
            $table->string('expected_location')->comment('预期位置');
            $table->string('actual_location')->nullable()->comment('实际位置');
            
            // 用户分配信息
            $table->foreignId('expected_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('actual_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // 资产状态信息
            $table->enum('expected_status', ['available', 'assigned', 'maintenance', 'disposed'])->comment('预期状态');
            $table->enum('actual_status', ['available', 'assigned', 'maintenance', 'disposed'])->nullable()->comment('实际状态');
            
            // 检查项目
            $table->boolean('condition_good')->default(false)->comment('状况良好');
            $table->boolean('condition_fair')->default(false)->comment('状况一般');
            $table->boolean('condition_poor')->default(false)->comment('状况差');
            $table->boolean('needs_maintenance')->default(false)->comment('需要维修');
            $table->boolean('needs_replacement')->default(false)->comment('需要更换');
            
            // 附加信息
            $table->text('notes')->nullable()->comment('备注');
            $table->json('photos')->nullable()->comment('照片路径');
            $table->text('damage_description')->nullable()->comment('损坏描述');
            $table->decimal('estimated_repair_cost', 12, 2)->nullable()->comment('预估维修成本');
            
            // 盘点过程
            $table->decimal('gps_latitude', 10, 8)->nullable()->comment('GPS纬度');
            $table->decimal('gps_longitude', 10, 8)->nullable()->comment('GPS经度');
            $table->timestamp('scan_time')->nullable()->comment('扫描时间');
            $table->string('qr_code_scan_result')->nullable()->comment('二维码扫描结果');
            
            // 盘点结果处理
            $table->enum('action_taken', ['none', 'corrected', 'flagged', 'follow_up', 'adjusted'])->default('none')->comment('采取的行动');
            $table->text('action_details')->nullable()->comment('行动详情');
            $table->foreignId('action_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('action_time')->nullable()->comment('行动时间');
            
            // 审核信息
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending')->comment('审核状态');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->text('review_notes')->nullable()->comment('审核备注');
            
            $table->timestamps();
            $table->softDeletes();
            
            // 索引优化
            $table->index('inventory_number');
            $table->index('inventory_date');
            $table->index('inventory_type');
            $table->index('physical_status');
            $table->index('status_match');
            $table->index('review_status');
            $table->index(['asset_id', 'inventory_date']);
            $table->index(['inventory_task_id', 'asset_id']);
        });
    }

    /**
     * 回滚数据库迁移
     */
    public function down()
    {
        Schema::dropIfExists('inventory_records');
    }
}