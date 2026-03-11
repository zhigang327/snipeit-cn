<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id')->comment('资产ID');
            $table->unsignedBigInteger('user_id')->comment('操作人ID');
            $table->enum('action', ['create', 'checkout', 'checkin', 'update', 'delete', 'maintenance', 'scrapped'])->comment('操作类型');
            $table->text('notes')->nullable()->comment('备注');
            $table->unsignedBigInteger('target_user_id')->nullable()->comment('目标用户ID(领用/归还时)');
            $table->unsignedBigInteger('target_department_id')->nullable()->comment('目标部门ID');
            $table->json('old_values')->nullable()->comment('变更前的值');
            $table->json('new_values')->nullable()->comment('变更后的值');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('asset_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_histories');
    }
};
