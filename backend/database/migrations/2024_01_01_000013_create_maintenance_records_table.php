<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->comment('资产ID');
            $table->unsignedBigInteger('reported_by')->comment('报修人ID');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('维修负责人ID');
            $table->string('title')->comment('维修标题');
            $table->text('description')->nullable()->comment('故障描述');
            $table->text('diagnosis')->nullable()->comment('故障诊断');
            $table->text('solution')->nullable()->comment('解决方案');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending')->comment('维修状态');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->comment('优先级');
            $table->enum('type', ['hardware', 'software', 'network', 'other'])->default('hardware')->comment('维修类型');
            $table->date('reported_date')->comment('报修日期');
            $table->date('start_date')->nullable()->comment('开始维修日期');
            $table->date('completed_date')->nullable()->comment('完成日期');
            $table->integer('estimated_hours')->nullable()->comment('预估维修时长(小时)');
            $table->integer('actual_hours')->nullable()->comment('实际维修时长(小时)');
            $table->decimal('estimated_cost', 12, 2)->nullable()->comment('预估费用');
            $table->decimal('actual_cost', 12, 2)->nullable()->comment('实际费用');
            $table->string('vendor')->nullable()->comment('维修供应商');
            $table->string('vendor_contact')->nullable()->comment('供应商联系方式');
            $table->text('parts_used')->nullable()->comment('使用配件');
            $table->text('notes')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人ID');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('asset_id');
            $table->index('reported_by');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
            $table->index('type');
            $table->index('reported_date');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};