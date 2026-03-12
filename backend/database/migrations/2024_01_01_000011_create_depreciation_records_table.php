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
        Schema::create('depreciation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->date('depreciation_date')->comment('折旧日期');
            $table->decimal('depreciation_amount', 12, 2)->comment('本期折旧额');
            $table->decimal('accumulated_depreciation', 12, 2)->comment('累计折旧');
            $table->decimal('book_value', 12, 2)->comment('账面价值');
            $table->text('notes')->nullable()->comment('备注');
            $table->foreignId('created_by')->constrained('users')->comment('创建人');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_records');
    }
};
