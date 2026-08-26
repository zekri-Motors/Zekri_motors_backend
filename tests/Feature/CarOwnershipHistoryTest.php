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

class CarOwnershipHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_access_car_ownership_history(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create user with no permissions
        $user = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $supplier = Supplier::create(['name' => 'Supplier A']);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
        ]);
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'manufacture_year' => '2021',
            'foreign_purchase_price' => 15000,
            'sale_price' => 18000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/cars/{$car->id}/ownership-history");
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_retrieve_car_ownership_history(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $supplier = Supplier::create(['name' => 'Supplier A']);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
        ]);
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'manufacture_year' => '2021',
            'foreign_purchase_price' => 15000,
            'sale_price' => 18000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        $customer1 = Customer::create([
            'name' => 'First Owner',
            'national_id' => '999111',
            'passport_no' => 'P999111',
        ]);
        $order1 = Order::create([
            'order_number' => 'ORD-901',
            'customer_id' => $customer1->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        $customer2 = Customer::create([
            'name' => 'Second Owner',
            'national_id' => '999222',
            'passport_no' => 'P999222',
        ]);
        $order2 = Order::create([
            'order_number' => 'ORD-902',
            'customer_id' => $customer2->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $superadmin = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($superadmin);

        $response = $this->getJson("/api/cars/{$car->id}/ownership-history");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_id', $order2->id)
            ->assertJsonPath('data.0.order_number', 'ORD-902')
            ->assertJsonPath('data.0.customer.name', 'Second Owner')
            ->assertJsonPath('data.0.customer.national_id', '999222');

        $this->assertDatabaseMissing('orders', [
            'id' => $order1->id,
        ]);
    }
}
