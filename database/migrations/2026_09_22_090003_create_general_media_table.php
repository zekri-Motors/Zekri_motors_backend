<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_media', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['image', 'video'])->index();

            $table->string('url', 2048);
            $table->string('disk')->nullable();
            $table->string('path')->nullable();

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_media');
    }
};
