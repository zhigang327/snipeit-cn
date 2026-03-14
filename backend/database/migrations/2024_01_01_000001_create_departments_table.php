<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('部门名称');
            $table->string('code')->unique()->comment('部门编码');
            $table->text('description')->nullable()->comment('部门描述');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父部门ID');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('部门负责人ID');
            $table->integer('sort')->default(0)->comment('排序');
            $table->string('location')->nullable()->comment('部门地址');
            $table->string('phone')->nullable()->comment('部门电话');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            $table->softDeletes();

            // 多级部门关系（manager_id 外键在 users 表创建后的迁移中添加）
            $table->foreign('parent_id')->references('id')->on('departments')->onDelete('cascade');

            // 索引
            $table->index('parent_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
