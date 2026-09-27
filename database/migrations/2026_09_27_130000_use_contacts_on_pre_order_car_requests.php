<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pre_order_car_requests', 'customer_id')) {
            return;
        }

        // Old rows were tied to customers; pre-orders now use contacts only.
        DB::table('pre_order_car_requests')->delete();

        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropUnique(['pre_order_car_id', 'customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('contact_id')->after('pre_order_car_id')->constrained()->restrictOnDelete();
            $table->unique(['pre_order_car_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pre_order_car_requests', 'contact_id')) {
            return;
        }

        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropUnique(['pre_order_car_id', 'contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('customer_id')->after('pre_order_car_id')->constrained()->restrictOnDelete();
            $table->unique(['pre_order_car_id', 'customer_id']);
        });
    }
};
