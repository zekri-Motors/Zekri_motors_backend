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

class CarsTableReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_access_cars_table(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Authenticate as a normal user without view cost permission (e.g. an agent)
        $agentUser = User::create([
            'name' => 'Agent User',
            'email' => 'agent@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $agentUser->assignRole('agent');

        Sanctum::actingAs($agentUser);

        $response = $this->getJson('/api/cars/table');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_cars_table_with_first_and_current_owner(): void
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
            'brand' => 'Ford',
            'model' => 'Mustang',
            'manufacture_year' => '2022',
            'foreign_purchase_price' => 20000,
            'sale_price' => 25000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        $customer1 = Customer::create([
            'name' => 'John Doe First Owner',
            'national_id' => '111222',
            'passport_no' => 'P111222',
        ]);

        // First order
        Order::create([
            'order_number' => 'ORD-101',
            'customer_id' => $customer1->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        $customer2 = Customer::create([
            'name' => 'Jane Smith Current Owner',
            'national_id' => '333444',
            'passport_no' => 'P333444',
        ]);

        // Second order (makes it latest)
        Order::create([
            'order_number' => 'ORD-102',
            'customer_id' => $customer2->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $superadmin = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/cars/table');

        $response->assertOk()
            ->assertJsonPath('data.0.brand', 'Ford')
            ->assertJsonPath('data.0.first_owner_name', 'Jane Smith Current Owner')
            ->assertJsonPath('data.0.first_owner_passport_no', 'P333444')
            ->assertJsonPath('data.0.first_owner_national_id', '333444')
            ->assertJsonPath('data.0.current_owner_name', 'Jane Smith Current Owner')
            ->assertJsonPath('data.0.current_owner_passport_no', 'P333444')
            ->assertJsonPath('data.0.current_owner_national_id', '333444');
    }
}
