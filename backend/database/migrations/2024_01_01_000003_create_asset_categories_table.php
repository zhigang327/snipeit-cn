<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分类名称');
            $table->string('code')->unique()->comment('分类编码');
            $table->text('description')->nullable()->comment('分类描述');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父分类ID');
            $table->string('image')->nullable()->comment('分类图片');
            $table->integer('sort')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('asset_categories')->onDelete('cascade');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
