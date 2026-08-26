<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCarStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_car_defaults_to_shipping_status(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Sanctum::actingAs(User::where('email', 'superadmin@zaki.com')->firstOrFail());

        [$supplier, $batch] = $this->supplierAndBatch();

        $response = $this->postJson('/api/cars', [
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => 2024,
            'foreign_purchase_price' => 12000,
            'sale_price' => 16000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Car::STATUS_SHIPPING);

        $this->assertDatabaseHas('cars', [
            'id' => $response->json('data.id'),
            'status' => Car::STATUS_SHIPPING,
        ]);
    }

    public function test_order_for_different_current_owner_marks_car_as_sold(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Sanctum::actingAs(User::where('email', 'superadmin@zaki.com')->firstOrFail());

        [$supplier, $batch] = $this->supplierAndBatch();

        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'manufacture_year' => 2023,
            'foreign_purchase_price' => 14000,
            'sale_price' => 19000,
            'status' => Car::STATUS_SHIPPING,
        ]);

        $firstOwner = Customer::create([
            'name' => 'First Owner',
            'national_id' => 'NAT-001',
            'passport_no' => 'PAS-001',
        ]);
        $currentOwner = Customer::create([
            'name' => 'Current Owner',
            'national_id' => 'NAT-002',
            'passport_no' => 'PAS-002',
        ]);

        $this->postJson('/api/orders', [
            'customer_id' => $firstOwner->id,
            'car_id' => $car->id,
        ])->assertCreated();

        $this->assertSame(Car::STATUS_SHIPPING, $car->fresh()->status);

        $this->postJson('/api/orders', [
            'customer_id' => $currentOwner->id,
            'car_id' => $car->id,
        ])->assertCreated();

        $this->assertSame(Car::STATUS_SOLD, $car->fresh()->status);
        $this->assertSame($currentOwner->id, $car->fresh('firstOrder')->firstOrder->customer_id);
        $this->assertSame($currentOwner->id, $car->fresh('currentOrder')->currentOrder->customer_id);
    }

    /**
     * @return array{0: Supplier, 1: Batch}
     */
    private function supplierAndBatch(): array
    {
        $supplier = Supplier::create(['name' => 'Supplier A']);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1,
        ]);

        return [$supplier, $batch];
    }
}
