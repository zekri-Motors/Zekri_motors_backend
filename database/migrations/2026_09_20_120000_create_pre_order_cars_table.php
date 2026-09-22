<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_order_cars', function (Blueprint $table) {
            $table->id();

            // Same supplier/container-opener entered once for the whole
            // uploaded sheet, mirroring the normal batch import flow.
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('container_opener_id')->nullable()->constrained()->nullOnDelete();

            // Car spec only — deliberately no VIN, tracking number, or any
            // owner/customer field: nothing physical has been sourced yet.
            $table->string('brand');
            $table->string('model');
            $table->string('finition')->nullable();
            $table->unsignedSmallInteger('manufacture_year');
            $table->string('color')->nullable();

            // Single price shown to the customer (not a purchase/sale pair).
            $table->decimal('price', 12, 2);

            // draft   -> just imported, not visible to customers yet
            // pending -> published, open for customer requests
            // completed -> one request was approved; a real Batch/Car/Order
            //              now exists for the winning customer
            $table->enum('status', ['draft', 'pending', 'completed'])
                ->default('draft')
                ->index();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_order_cars');
    }
};
