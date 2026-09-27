<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure pre_order_cars to contain exactly:
 *   brand, model, finition, manufacture_year, color,
 *   price, customs_fees (جمركة — سيارات جديدة),
 *   customs_fees_under_three (جمركة +3 — أقل من 3 سنوات),
 *   preparation_days (مدة التجهيز), shipping_days (مدة الشحن)
 *
 * Removes: supplier_id, container_opener_id, notes
 * (published_at, created_by, timestamps are kept)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_order_cars', function (Blueprint $table) {
            // Drop foreign keys first before dropping columns
            if (Schema::hasColumn('pre_order_cars', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }

            if (Schema::hasColumn('pre_order_cars', 'container_opener_id')) {
                $table->dropForeign(['container_opener_id']);
                $table->dropColumn('container_opener_id');
            }

            if (Schema::hasColumn('pre_order_cars', 'notes')) {
                $table->dropColumn('notes');
            }
        });

        Schema::table('pre_order_cars', function (Blueprint $table) {
            // Add customs_fees_under_three (جمركة +3)
            if (! Schema::hasColumn('pre_order_cars', 'customs_fees_under_three')) {
                $table->decimal('customs_fees_under_three', 12, 2)->default(0)->after('customs_fees');
            }

            // Add preparation_days (مدة التجهيز — in days)
            if (! Schema::hasColumn('pre_order_cars', 'preparation_days')) {
                $table->unsignedSmallInteger('preparation_days')->default(0)->after('customs_fees_under_three');
            }

            // Add shipping_days (مدة الشحن — in days)
            if (! Schema::hasColumn('pre_order_cars', 'shipping_days')) {
                $table->unsignedSmallInteger('shipping_days')->default(0)->after('preparation_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->dropColumn(['customs_fees_under_three', 'preparation_days', 'shipping_days']);
        });

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('container_opener_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
        });
    }
};
