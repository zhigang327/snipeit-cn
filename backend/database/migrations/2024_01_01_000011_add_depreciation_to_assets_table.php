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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('depreciation_method')->nullable()->comment('折旧方法: straight_line, declining_balance');
            $table->decimal('salvage_value', 12, 2)->nullable()->comment('残值');
            $table->integer('useful_life_years')->nullable()->comment('使用年限(年)');
            $table->decimal('current_book_value', 12, 2)->nullable()->comment('当前账面价值');
            $table->date('last_depreciation_date')->nullable()->comment('上次折旧日期');
            $table->decimal('accumulated_depreciation', 12, 2)->default(0)->comment('累计折旧');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'depreciation_method',
                'salvage_value',
                'useful_life_years',
                'current_book_value',
                'last_depreciation_date',
                'accumulated_depreciation',
            ]);
        });
    }
};
