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
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // pending  -> awaiting a staff decision
            // approved -> this is the winning customer (only one per car)
            // rejected -> either lost automatically when another request
            //             on the same car was approved, or rejected manually
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();

            $table->text('notes')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One request per customer per pre-order car — a customer
            // updates/cancels their existing request instead of duplicating it.
            $table->unique(['pre_order_car_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_order_car_requests');
    }
};
