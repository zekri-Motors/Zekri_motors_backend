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

class CarOwnerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_car_with_first_and_current_owner_with_all_info_if_authorized(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Create a supplier
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
        ]);

        // 2. Create a batch
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
            'status' => 'partial',
        ]);

        // 3. Create a car
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Camry',
            'manufacture_year' => '2020',
            'foreign_purchase_price' => 10000,
            'sale_price' => 15000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        // 4. Create first customer (first owner)
        $customer1 = Customer::create([
            'name' => 'First Customer',
            'phone' => '1234567890',
            'email' => 'first@example.com',
            'national_id' => 'NAT111',
            'passport_no' => 'PAS111',
            'address' => '123 First St',
        ]);

        // Place first order on the car
        Order::create([
            'order_number' => 'ORD-001',
            'customer_id' => $customer1->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        // 5. Create second customer (current owner)
        $customer2 = Customer::create([
            'name' => 'Second Customer',
            'phone' => '0987654321',
            'email' => 'second@example.com',
            'national_id' => 'NAT222',
            'passport_no' => 'PAS222',
            'address' => '456 Second St',
        ]);

        // Place second order on the car (which becomes the current order)
        Order::create([
            'order_number' => 'ORD-002',
            'customer_id' => $customer2->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        // Authenticate as super-admin (authorized to see operational data)
        $superadmin = User::where('email', 'superadmin@zaki.com')->firstOrFail();
        Sanctum::actingAs($superadmin);

        // GET single car
        $response = $this->getJson("/api/cars/{$car->id}");

        $response->assertOk()
            ->assertJsonPath('data.first_owner.id', $customer2->id)
            ->assertJsonPath('data.first_owner.name', $customer2->name)
            ->assertJsonPath('data.first_owner.passport_no', $customer2->passport_no)
            ->assertJsonPath('data.first_owner.national_id', $customer2->national_id)
            ->assertJsonPath('data.first_owner.email', $customer2->email)
            ->assertJsonPath('data.first_owner.address', $customer2->address)
            ->assertJsonPath('data.current_owner.id', $customer2->id)
            ->assertJsonPath('data.current_owner.name', $customer2->name)
            ->assertJsonPath('data.current_owner.passport_no', $customer2->passport_no)
            ->assertJsonPath('data.current_owner.national_id', $customer2->national_id)
            ->assertJsonPath('data.current_owner.email', $customer2->email)
            ->assertJsonPath('data.current_owner.address', $customer2->address);

        // GET cars list
        $responseList = $this->getJson('/api/cars');
        $responseList->assertOk()
            ->assertJsonPath('data.0.first_owner.name', $customer2->name)
            ->assertJsonPath('data.0.current_owner.name', $customer2->name);
    }

    public function test_agent_can_view_ownership_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create a supplier
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
        ]);

        // Create a batch
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
            'status' => 'partial',
        ]);

        // Create a car
        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Camry',
            'manufacture_year' => '2020',
            'foreign_purchase_price' => 10000,
            'sale_price' => 15000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        // Create customer and order
        $customer = Customer::create([
            'name' => 'First Customer',
            'phone' => '1234567890',
            'email' => 'first@example.com',
            'national_id' => 'NAT111',
            'passport_no' => 'PAS111',
            'address' => '123 First St',
        ]);

        Order::create([
            'order_number' => 'ORD-001',
            'customer_id' => $customer->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        // Create an agent user (has no suppliers.view permission)
        $agentUser = User::create([
            'name' => 'Agent User',
            'email' => 'agent@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $agentUser->assignRole('agent');

        Sanctum::actingAs($agentUser);

        // GET single car
        $response = $this->getJson("/api/cars/{$car->id}");
        $response->assertOk()
            ->assertJsonPath('data.first_owner.id', $customer->id)
            ->assertJsonPath('data.first_owner.name', $customer->name);

        // GET cars list
        $responseList = $this->getJson('/api/cars');
        $responseList->assertOk()
            ->assertJsonPath('data.0.first_owner.id', $customer->id)
            ->assertJsonPath('data.0.first_owner.name', $customer->name);
    }
}
