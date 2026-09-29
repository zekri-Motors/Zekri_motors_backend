<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_media_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_media_id')->constrained('car_media')->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('cars')->cascadeOnDelete();
            $table->foreignId('pre_order_car_id')->nullable()->constrained('pre_order_cars')->cascadeOnDelete();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['car_media_id', 'car_id']);
            $table->unique(['car_media_id', 'pre_order_car_id']);
            $table->index(['car_id', 'is_cover']);
            $table->index(['pre_order_car_id', 'is_cover']);
        });

        // Migrate links created by the old one-to-many schema before removing
        // the ownership columns from car_media.
        DB::table('car_media')->select([
            'id', 'car_id', 'pre_order_car_id', 'is_cover', 'sort_order', 'created_at', 'updated_at',
        ])->orderBy('id')->each(function (object $media): void {
            if ($media->car_id !== null) {
                DB::table('car_media_links')->insert([
                    'car_media_id' => $media->id,
                    'car_id' => $media->car_id,
                    'is_cover' => $media->is_cover,
                    'sort_order' => $media->sort_order,
                    'created_at' => $media->created_at,
                    'updated_at' => $media->updated_at,
                ]);
            }

            if ($media->pre_order_car_id !== null) {
                DB::table('car_media_links')->insert([
                    'car_media_id' => $media->id,
                    'pre_order_car_id' => $media->pre_order_car_id,
                    'is_cover' => $media->is_cover,
                    'sort_order' => $media->sort_order,
                    'created_at' => $media->created_at,
                    'updated_at' => $media->updated_at,
                ]);
            }
        });

        Schema::table('car_media', function (Blueprint $table) {
            $table->dropForeign(['car_id']);
            $table->dropForeign(['pre_order_car_id']);
            $table->dropIndex(['car_id', 'type']);
            $table->dropIndex(['pre_order_car_id']);
            $table->dropColumn(['car_id', 'pre_order_car_id', 'is_cover', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('car_media', function (Blueprint $table) {
            $table->foreignId('car_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pre_order_car_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
        });

        DB::table('car_media_links')->orderBy('id')->get()->each(function (object $link): void {
            $media = DB::table('car_media')->where('id', $link->car_media_id)->first();
            if (! $media || $media->car_id !== null || $media->pre_order_car_id !== null) {
                return;
            }

            DB::table('car_media')->where('id', $link->car_media_id)->update([
                'car_id' => $link->car_id,
                'pre_order_car_id' => $link->pre_order_car_id,
                'is_cover' => $link->is_cover,
                'sort_order' => $link->sort_order,
            ]);
        });

        Schema::dropIfExists('car_media_links');
    }
};
