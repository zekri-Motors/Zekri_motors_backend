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

        /*
         * Old rows were tied to customers.
         * Pre-order requests now use contacts.
         */
        DB::table('pre_order_car_requests')->delete();

        /*
         * Find and remove any FOREIGN KEY that uses customer_id.
         *
         * We don't assume the constraint name because the production
         * database may have a different name.
         */
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

        /*
         * Now the unique index can be removed safely.
         */
        $indexes = DB::select(
            "SHOW INDEX FROM `pre_order_car_requests`"
        );

        $uniqueIndexExists = collect($indexes)->contains(
            fn ($index) =>
                $index->Key_name ===
                'pre_order_car_requests_pre_order_car_id_customer_id_unique'
        );

        if ($uniqueIndexExists) {
            Schema::table('pre_order_car_requests', function (Blueprint $table) {
                $table->dropUnique(
                    'pre_order_car_requests_pre_order_car_id_customer_id_unique'
                );
            });
        }

        /*
         * Remove customer_id.
         */
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        /*
         * Add contact_id.
         */
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('contact_id')
                ->after('pre_order_car_id')
                ->constrained('contacts')
                ->restrictOnDelete();

            $table->unique([
                'pre_order_car_id',
                'contact_id',
            ]);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pre_order_car_requests', 'contact_id')) {
            return;
        }

        /*
         * Remove contact foreign key.
         */
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

        /*
         * Remove contact unique index.
         */
        $indexes = DB::select(
            "SHOW INDEX FROM `pre_order_car_requests`"
        );

        $uniqueIndexExists = collect($indexes)->contains(
            fn ($index) =>
                $index->Key_name ===
                'pre_order_car_requests_pre_order_car_id_contact_id_unique'
        );

        if ($uniqueIndexExists) {
            Schema::table('pre_order_car_requests', function (Blueprint $table) {
                $table->dropUnique(
                    'pre_order_car_requests_pre_order_car_id_contact_id_unique'
                );
            });
        }

        /*
         * Remove contact_id.
         */
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->dropColumn('contact_id');
        });

        /*
         * Restore customer_id.
         */
        Schema::table('pre_order_car_requests', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->after('pre_order_car_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->unique([
                'pre_order_car_id',
                'customer_id',
            ]);
        });
    }
};