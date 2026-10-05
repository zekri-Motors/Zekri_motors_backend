<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_order_car_requests', function (Blueprint $table): void {
            $table->decimal('paid_amount', 12, 2)
                ->default(0)
                ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('pre_order_car_requests', function (Blueprint $table): void {
            $table->dropColumn('paid_amount');
        });
    }
};
