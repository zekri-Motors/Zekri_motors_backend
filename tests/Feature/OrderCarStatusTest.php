<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
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

        $firstOrderId = $this->postJson('/api/orders', [
            'customer_id' => $firstOwner->id,
            'car_id' => $car->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame(Car::STATUS_SHIPPING, $car->fresh()->status);

        $currentOrderResponse = $this->postJson('/api/orders', [
            'customer_id' => $currentOwner->id,
            'car_id' => $car->id,
        ])->assertCreated();

        $this->assertSame(Car::STATUS_SOLD, $car->fresh()->status);
        $currentOrderResponse->assertJsonPath('data.status', Order::STATUS_SOLD);
        $this->assertSame($firstOwner->id, $car->fresh('firstOrder')->firstOrder->customer_id);
        $this->assertSame($currentOwner->id, $car->fresh('currentOrder')->currentOrder->customer_id);
        $this->assertSame(Order::STATUS_SOLD, $car->fresh('currentOrder')->currentOrder->status);
        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'customer_id' => $firstOwner->id,
            'paid_amount' => 0,
            'remaining_amount' => 0,
        ]);
        $this->assertSame(2, Order::where('car_id', $car->id)->count());
    }

    public function test_orders_can_be_filtered_by_batch_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Sanctum::actingAs(User::where('email', 'superadmin@zaki.com')->firstOrFail());

        [$supplier, $batchOne] = $this->supplierAndBatch();
        $batchTwo = Batch::create(['supplier_id' => $supplier->id, 'exchange_rate' => 1]);

        foreach ([$batchOne, $batchTwo] as $index => $batch) {
            $car = Car::create([
                'batch_id' => $batch->id,
                'supplier_id' => $supplier->id,
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'manufacture_year' => 2024,
                'foreign_purchase_price' => 12000,
                'sale_price' => 16000,
                'status' => Car::STATUS_SHIPPING,
            ]);
            $firstCustomer = Customer::create([
                'name' => "First {$index}",
                'national_id' => "NAT-F{$index}",
                'passport_no' => "PAS-F{$index}",
            ]);
            $currentCustomer = Customer::create([
                'name' => "Current {$index}",
                'national_id' => "NAT-C{$index}",
                'passport_no' => "PAS-C{$index}",
            ]);

            Order::create([
                'order_number' => "ORD-FIRST-{$index}",
                'customer_id' => $firstCustomer->id,
                'car_id' => $car->id,
                'status' => Order::STATUS_SHIPPING,
            ]);
            Order::create([
                'order_number' => "ORD-CURRENT-{$index}",
                'customer_id' => $currentCustomer->id,
                'car_id' => $car->id,
                'status' => Order::STATUS_SOLD,
            ]);
        }

        $response = $this->getJson("/api/orders?batch_id={$batchOne->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($batchOne->id, $response->json('data.0.car.batch_id'));
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
