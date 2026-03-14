<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisposalRecordsTable extends Migration
{
    /**
     * 运行数据库迁移
     */
    public function up()
    {
        Schema::create('disposal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('disposal_number')->unique()->comment('报废编号');
            $table->enum('disposal_type', ['sold', 'scrapped', 'donated', 'transferred', 'lost'])->comment('报废类型');
            $table->date('disposal_date')->comment('报废日期');
            $table->decimal('disposal_amount', 12, 2)->nullable()->comment('报废金额');
            $table->decimal('salvage_value', 12, 2)->nullable()->comment('残值');
            $table->decimal('book_value', 12, 2)->comment('账面价值');
            $table->decimal('gain_loss', 12, 2)->comment('处置损益');
            $table->text('reason')->comment('报废原因');
            $table->text('description')->nullable()->comment('报废描述');
            $table->string('recipient_name')->nullable()->comment('接收方名称');
            $table->string('recipient_contact')->nullable()->comment('接收方联系方式');
            $table->string('document_number')->nullable()->comment('相关单据号');
            $table->string('approval_number')->nullable()->comment('审批单号');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('rejection_reason')->nullable()->comment('拒绝原因');
            
            // 资产处置后的去向信息
            $table->string('final_location')->nullable()->comment('最终去向');
            $table->text('handover_notes')->nullable()->comment('交接说明');
            $table->text('environmental_impact')->nullable()->comment('环境影响说明');
            
            $table->timestamps();
            $table->softDeletes();
            
            // 索引优化
            $table->index('disposal_number');
            $table->index('disposal_date');
            $table->index('status');
            $table->index(['asset_id', 'status']);
        });
    }

    /**
     * 回滚数据库迁移
     */
    public function down()
    {
        Schema::dropIfExists('disposal_records');
    }
}