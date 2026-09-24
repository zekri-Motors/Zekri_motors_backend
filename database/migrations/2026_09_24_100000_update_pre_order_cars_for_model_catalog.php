<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->decimal('customs_fees', 12, 2)->default(0)->after('price');

            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['container_opener_id']);
        });

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->foreignId('container_opener_id')->nullable()->change();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('container_opener_id')->references('id')->on('container_openers')->nullOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['container_opener_id']);
            $table->dropColumn('customs_fees');
        });

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->foreignId('container_opener_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('container_opener_id')->references('id')->on('container_openers')->nullOnDelete();
        });
    }
};
