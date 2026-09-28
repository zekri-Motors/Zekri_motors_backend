<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change preparation_days and shipping_days from unsignedSmallInteger to string
 * so they can hold textual values like "15 - 30 يوم" instead of plain numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->string('preparation_days')->default('')->change();
            $table->string('shipping_days')->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->unsignedSmallInteger('preparation_days')->default(0)->change();
            $table->unsignedSmallInteger('shipping_days')->default(0)->change();
        });
    }
};
