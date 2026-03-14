<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique()->comment('资产标签');
            $table->string('name')->comment('资产名称');
            $table->text('description')->nullable()->comment('资产描述');
            $table->unsignedBigInteger('category_id')->comment('资产分类ID');
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('供应商ID');
            $table->decimal('purchase_price', 12, 2)->default(0)->comment('采购价格');
            $table->date('purchase_date')->nullable()->comment('采购日期');
            $table->string('brand')->nullable()->comment('品牌');
            $table->string('model')->nullable()->comment('型号');
            $table->string('serial_number')->nullable()->comment('序列号');
            $table->string('warranty_expiry')->nullable()->comment('保修到期');
            $table->unsignedBigInteger('department_id')->nullable()->comment('使用部门ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('使用人ID');
            $table->string('location')->nullable()->comment('存放位置');
            $table->enum('status', ['ready', 'assigned', 'maintenance', 'broken', 'lost', 'scrapped'])->default('ready')->comment('状态');
            $table->date('checkout_date')->nullable()->comment('领用日期');
            $table->date('expected_checkin_date')->nullable()->comment('预计归还日期');
            $table->text('notes')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人ID');
            $table->string('image')->nullable()->comment('资产图片');
            $table->integer('warranty_months')->default(12)->comment('保修期(月)');
            $table->string('qr_code')->nullable()->comment('二维码路径');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('asset_categories')->onDelete('restrict');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('asset_tag');
            $table->index('category_id');
            $table->index('department_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
