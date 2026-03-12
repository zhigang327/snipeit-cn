<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('borrow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('borrower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('borrow_purpose'); // 借用目的
            $table->date('borrow_date'); // 借用日期
            $table->date('expected_return_date'); // 预计归还日期
            $table->date('actual_return_date')->nullable(); // 实际归还日期
            $table->decimal('deposit_amount', 10, 2)->default(0.00); // 押金金额
            $table->boolean('deposit_returned')->default(false); // 押金是否已退还
            $table->enum('status', [
                'pending',     // 待审批
                'approved',    // 已批准
                'rejected',    // 已拒绝
                'borrowed',    // 已借出
                'returned',    // 已归还
                'overdue',     // 逾期未还
                'cancelled'    // 已取消
            ])->default('pending');
            $table->text('borrow_conditions')->nullable(); // 借用条件
            $table->text('rejection_reason')->nullable(); // 拒绝原因
            $table->text('return_notes')->nullable(); // 归还备注
            $table->text('damage_description')->nullable(); // 损坏描述
            $table->decimal('damage_fee', 10, 2)->default(0.00); // 损坏赔偿金额
            $table->boolean('damage_resolved')->default(false); // 损坏是否已处理
            $table->timestamps();
            $table->softDeletes();

            // 添加索引
            $table->index('asset_id');
            $table->index('borrower_id');
            $table->index('status');
            $table->index('expected_return_date');
            $table->index(['status', 'expected_return_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_records');
    }
};