<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('合同名称');
            $table->string('code')->unique()->comment('合同编号');
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->enum('type', ['purchase', 'maintenance', 'lease'])->default('purchase')->comment('合同类型');
            $table->date('start_date')->comment('开始日期');
            $table->date('end_date')->comment('结束日期');
            $table->decimal('amount', 12, 2)->default(0)->comment('合同金额');
            $table->string('currency', 3)->default('CNY')->comment('货币');
            $table->text('terms')->nullable()->comment('合同条款');
            $table->text('notes')->nullable()->comment('备注');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->comment('状态');
            $table->string('file_path')->nullable()->comment('合同文件路径');
            $table->date('notify_date')->nullable()->comment('提醒日期');
            $table->boolean('is_notified')->default(false)->comment('是否已提醒');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index('code');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
