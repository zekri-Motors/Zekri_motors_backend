<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pre_order_car_requests', 'customer_id')) {
            return;
        }

        // Old pre-order requests are no longer compatible with the new
        // contact-based structure.
        DB::table('pre_order_car_requests')->delete();

        // Drop the foreign key first.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropForeign(
                'pre_order_car_requests_customer_id_foreign'
            );
        });

        // Then drop the unique index.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropUnique(
                'pre_order_car_requests_pre_order_car_id_customer_id_unique'
            );
        });

        // Finally remove customer_id.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        // Add contact_id.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('contact_id')
                ->after('pre_order_car_id')
                ->constrained('contacts')
                ->restrictOnDelete();

            $table->unique([
                'pre_order_car_id',
                'contact_id'
            ]);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pre_order_car_requests', 'contact_id')) {
            return;
        }

        // Drop contact foreign key first.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropForeign(
                'pre_order_car_requests_contact_id_foreign'
            );
        });

        // Drop contact unique index.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropUnique(
                'pre_order_car_requests_pre_order_car_id_contact_id_unique'
            );
        });

        // Remove contact_id.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropColumn('contact_id');
        });

        // Restore customer_id.
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->after('pre_order_car_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->unique([
                'pre_order_car_id',
                'customer_id'
            ]);
        });
    }
};