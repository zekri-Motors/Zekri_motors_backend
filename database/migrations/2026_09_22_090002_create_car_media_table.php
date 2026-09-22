<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('car_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['image', 'video'])->index();

            // The URL the frontend uses directly — either a public storage
            // URL (uploaded file) or an external link, depending on source.
            $table->string('url', 2048);

            // Set only when the file was actually uploaded to our storage,
            // so we know what to delete from disk when the row is deleted.
            // Both stay NULL for an external URL (e.g. a YouTube link).
            $table->string('disk')->nullable();
            $table->string('path')->nullable();

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes

            $table->string('title')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['car_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_media');
    }
};
