<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_order_car_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pre_order_car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();

            // draft     -> saved but not yet submitted for processing
            // pending   -> submitted, awaiting staff decision
            // completed -> approved; remains a pre-order record only (no order/car/batch)
            $table->enum('status', ['draft', 'pending', 'completed'])
                ->default('draft')
                ->index();

            $table->text('notes')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One request per contact per pre-order car.
            $table->unique(['pre_order_car_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_order_car_requests');
    }
};
