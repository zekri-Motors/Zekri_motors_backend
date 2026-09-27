<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pre_order_cars', 'status')) {
            return;
        }

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->index();
        });

        DB::table('pre_order_cars')
            ->whereIn('status', ['pending', 'completed'])
            ->update(['published_at' => now()]);

        Schema::table('pre_order_cars', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        if (! Schema::hasColumn('pre_order_car_requests', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pre_order_car_requests MODIFY COLUMN status ENUM('draft', 'pending', 'completed', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");

            DB::table('pre_order_car_requests')
                ->where('status', 'approved')
                ->update(['status' => 'completed']);

            DB::table('pre_order_car_requests')
                ->where('status', 'rejected')
                ->update(['status' => 'draft']);

            DB::statement("ALTER TABLE pre_order_car_requests MODIFY COLUMN status ENUM('draft', 'pending', 'completed') NOT NULL DEFAULT 'draft'");

            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('pre_order_car_requests_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_order_car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'pending', 'completed'])
                ->default('draft')
                ->index();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['pre_order_car_id', 'customer_id']);
        });

        foreach (DB::table('pre_order_car_requests')->orderBy('id')->get() as $row) {
            $status = match ($row->status) {
                'approved' => 'completed',
                'rejected' => 'draft',
                default => $row->status === 'pending' ? 'pending' : 'draft',
            };

            DB::table('pre_order_car_requests_new')->insert([
                'id' => $row->id,
                'pre_order_car_id' => $row->pre_order_car_id,
                'customer_id' => $row->customer_id,
                'status' => $status,
                'notes' => $row->notes,
                'decided_by' => $row->decided_by,
                'decided_at' => $row->decided_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('pre_order_car_requests');
        Schema::rename('pre_order_car_requests_new', 'pre_order_car_requests');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (Schema::hasColumn('pre_order_cars', 'published_at')) {
            Schema::table('pre_order_cars', function (Blueprint $table) {
                $table->enum('status', ['draft', 'pending', 'completed'])
                    ->default('draft')
                    ->index();
            });

            DB::table('pre_order_cars')
                ->whereNotNull('published_at')
                ->update(['status' => 'pending']);

            Schema::table('pre_order_cars', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }

        if (! Schema::hasColumn('pre_order_car_requests', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pre_order_car_requests MODIFY COLUMN status ENUM('draft', 'pending', 'completed', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");

            DB::table('pre_order_car_requests')
                ->where('status', 'completed')
                ->update(['status' => 'approved']);

            DB::table('pre_order_car_requests')
                ->where('status', 'draft')
                ->update(['status' => 'pending']);

            DB::statement("ALTER TABLE pre_order_car_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }
};
