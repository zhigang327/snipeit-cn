<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('盘点名称');
            $table->text('description')->nullable()->comment('盘点描述');
            $table->unsignedBigInteger('department_id')->nullable()->comment('盘点部门');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending')->comment('状态');
            $table->unsignedBigInteger('created_by')->comment('创建人');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->comment('盘点ID');
            $table->unsignedBigInteger('asset_id')->comment('资产ID');
            $table->string('expected_location')->comment('预期位置');
            $table->string('actual_location')->comment('实际位置');
            $table->enum('status', ['found', 'not_found', 'lost', 'damaged'])->default('found')->comment('盘点状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->unsignedBigInteger('scanned_by')->comment('扫描人');
            $table->timestamp('scanned_at')->comment('扫描时间');
            $table->timestamps();

            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('scanned_by')->references('id')->on('users')->onDelete('restrict');

            $table->index('inventory_id');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
    }
};
