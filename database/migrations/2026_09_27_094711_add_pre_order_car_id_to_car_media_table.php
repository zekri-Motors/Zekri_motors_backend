<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_media', function (Blueprint $table) {
            // Make car_id nullable so a row can belong to a PreOrderCar
            // instead of a regular Car.
            $table->foreignId('car_id')->nullable()->change();

            // Link to a pre-order car catalog entry. Exactly one of
            // car_id / pre_order_car_id must be set per row — enforced at
            // the application layer (CarMedia model / controllers).
            $table->foreignId('pre_order_car_id')
                ->nullable()
                ->after('car_id')
                ->constrained('pre_order_cars')
                ->cascadeOnDelete();

            $table->index('pre_order_car_id');
        });
    }

    public function down(): void
    {
        Schema::table('car_media', function (Blueprint $table) {
            $table->dropForeign(['pre_order_car_id']);
            $table->dropIndex(['car_media_pre_order_car_id_index']);
            $table->dropColumn('pre_order_car_id');

            // Restore car_id to NOT NULL.
            $table->foreignId('car_id')->nullable(false)->change();
        });
    }
};
