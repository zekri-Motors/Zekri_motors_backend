<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            // taggable_id + taggable_type: works for CarMedia, GeneralMedia,
            // or any other model that gets tags()/morphToMany(Tag::class)
            // added later, without a new pivot table each time.
            $table->morphs('taggable');

            $table->timestamps();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
