<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pre_order_car_requests')) {
            return;
        }

        $hasCustomerId = Schema::hasColumn('pre_order_car_requests', 'customer_id');
        $hasContactId = Schema::hasColumn('pre_order_car_requests', 'contact_id');

        if ($hasCustomerId || ! $hasContactId) {
            if (! $hasCustomerId) {
                Schema::table('pre_order_car_requests', function (Blueprint $table): void {
                    $table->foreignId('customer_id')
                        ->after('pre_order_car_id')
                        ->constrained('customers')
                        ->restrictOnDelete();

                    $table->unique(['pre_order_car_id', 'customer_id']);
                });
            }

            return;
        }

        // Contact IDs cannot be safely converted to customer IDs.
        DB::table('pre_order_car_requests')->delete();

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $database = DB::getDatabaseName();

            $foreignKeys = DB::select(
                "SELECT DISTINCT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = 'pre_order_car_requests'
                   AND COLUMN_NAME = 'contact_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$database]
            );

            foreach ($foreignKeys as $foreignKey) {
                DB::statement(
                    'ALTER TABLE `pre_order_car_requests` DROP FOREIGN KEY `' .
                    $foreignKey->CONSTRAINT_NAME . '`'
                );
            }
        }

        $contactUniqueIndex = false;
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM `pre_order_car_requests`');
            $contactUniqueIndex = collect($indexes)->contains(
                fn ($index): bool => $index->Key_name ===
                    'pre_order_car_requests_pre_order_car_id_contact_id_unique'
            );
        }

        if ($contactUniqueIndex) {
            Schema::table('pre_order_car_requests', function (Blueprint $table): void {
                $table->dropUnique('pre_order_car_requests_pre_order_car_id_contact_id_unique');
            });
        }

        Schema::table('pre_order_car_requests', function (Blueprint $table): void {
            $table->dropColumn('contact_id');
            $table->foreignId('customer_id')
                ->after('pre_order_car_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->unique(['pre_order_car_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        // The preceding migration is the source of truth for rollback.
    }
};
