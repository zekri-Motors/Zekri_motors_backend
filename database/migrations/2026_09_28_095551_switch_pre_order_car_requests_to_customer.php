<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حذف جميع الطلبات المسبقة الموجودة لأنها مرتبطة بـ contact
        DB::table('pre_order_car_requests')->delete();

        // إزالة FK الخاص بـ contact_id (MySQL)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $database = DB::getDatabaseName();

            $foreignKeys = DB::select(
                "
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = 'pre_order_car_requests'
                  AND COLUMN_NAME = 'contact_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                ",
                [$database]
            );

            foreach ($foreignKeys as $foreignKey) {
                DB::statement(
                    'ALTER TABLE `pre_order_car_requests` DROP FOREIGN KEY `' .
                    $foreignKey->CONSTRAINT_NAME .
                    '`'
                );
            }
        }

        // إزالة unique index القديم (pre_order_car_id, contact_id)
        $indexes = DB::select("SHOW INDEX FROM `pre_order_car_requests`");

        $uniqueContactIndex = collect($indexes)->contains(
            fn ($index) =>
                $index->Key_name === 'pre_order_car_requests_pre_order_car_id_contact_id_unique'
        );

        if ($uniqueContactIndex) {
            Schema::table('pre_order_car_requests', function (Blueprint $table) {
                $table->dropUnique('pre_order_car_requests_pre_order_car_id_contact_id_unique');
            });
        }

        // حذف عمود contact_id وإضافة customer_id
        // Schema::table('pre_order_car_requests', function (Blueprint $table) {
        //     $table->dropColumn('contact_id');
        // });

        // Schema::table('pre_order_car_requests', function (Blueprint $table) {
        //     $table->foreignId('customer_id')
        //         ->after('pre_order_car_id')
        //         ->constrained('customers')
        //         ->restrictOnDelete();

        //     $table->unique(['pre_order_car_id', 'customer_id']);
        // });
    }

    public function down(): void
    {
        // إزالة FK الخاص بـ customer_id
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $database = DB::getDatabaseName();

            $foreignKeys = DB::select(
                "
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = 'pre_order_car_requests'
                  AND COLUMN_NAME = 'customer_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                ",
                [$database]
            );

            foreach ($foreignKeys as $foreignKey) {
                DB::statement(
                    'ALTER TABLE `pre_order_car_requests` DROP FOREIGN KEY `' .
                    $foreignKey->CONSTRAINT_NAME .
                    '`'
                );
            }
        }

        // إزالة unique index (pre_order_car_id, customer_id)
        $indexes = DB::select("SHOW INDEX FROM `pre_order_car_requests`");

        $uniqueCustomerIndex = collect($indexes)->contains(
            fn ($index) =>
                $index->Key_name === 'pre_order_car_requests_pre_order_car_id_customer_id_unique'
        );

        if ($uniqueCustomerIndex) {
            Schema::table('pre_order_car_requests', function (Blueprint $table) {
                $table->dropUnique('pre_order_car_requests_pre_order_car_id_customer_id_unique');
            });
        }

        // العودة لـ contact_id
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('contact_id')
                ->after('pre_order_car_id')
                ->constrained('contacts')
                ->restrictOnDelete();

            $table->unique(['pre_order_car_id', 'contact_id']);
        });
    }
};