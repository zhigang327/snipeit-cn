<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('供应商名称');
            $table->string('code')->unique()->comment('供应商编码');
            $table->string('contact')->nullable()->comment('联系人');
            $table->string('phone')->nullable()->comment('联系电话');
            $table->string('email')->nullable()->comment('邮箱');
            $table->text('address')->nullable()->comment('地址');
            $table->string('tax_number')->nullable()->comment('税号');
            $table->string('bank_name')->nullable()->comment('开户银行');
            $table->string('bank_account')->nullable()->comment('银行账号');
            $table->text('notes')->nullable()->comment('备注');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
