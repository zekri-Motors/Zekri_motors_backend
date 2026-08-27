<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\Batch;
use App\Models\Car;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentDataScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private Agent $agent1;
    private Agent $agent2;
    private Customer $customer1;
    private Customer $customer2;
    private Order $order1;
    private Order $order2;
    private CustomerPayment $payment1;
    private CustomerPayment $payment2;
    private AgentTransaction $agentTx1;
    private AgentTransaction $agentTx2;
    private TreasuryTransaction $treasuryTx1;
    private TreasuryTransaction $treasuryTx2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Create two agent users
        $this->user1 = User::create([
            'name' => 'Agent One User',
            'email' => 'agent1@zaki.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->user1->assignRole('agent');

        $this->user2 = User::create([
            'name' => 'Agent Two User',
            'email' => 'agent2@zaki.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->user2->assignRole('agent');

        // 2. Link Agent profiles
        $this->agent1 = Agent::create([
            'user_id' => $this->user1->id,
            'name' => 'Agent One',
            'email' => 'agent1@zaki.com',
            'phone' => '0511111111',
        ]);

        $this->agent2 = Agent::create([
            'user_id' => $this->user2->id,
            'name' => 'Agent Two',
            'email' => 'agent2@zaki.com',
            'phone' => '0522222222',
        ]);

        // 3. Create Customers
        $this->customer1 = Customer::create([
            'agent_id' => $this->agent1->id,
            'name' => 'Customer of Agent 1',
            'phone' => '0911111111',
            'email' => 'cust1@test.com',
            'national_id' => '1111111111111111',
            'passport_no' => 'P1111111',
        ]);

        $this->customer2 = Customer::create([
            'agent_id' => $this->agent2->id,
            'name' => 'Customer of Agent 2',
            'phone' => '0922222222',
            'email' => 'cust2@test.com',
            'national_id' => '2222222222222222',
            'passport_no' => 'P2222222',
        ]);

        // 4. Create Cars
        $supplier = Supplier::create([
            'name' => 'Supplier',
            'phone' => '0555555555',
            'email' => 'supplier@test.com',
        ]);
        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
            'status' => 'partial',
        ]);
        $car1 = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Hyundai',
            'model' => 'Elantra',
            'manufacture_year' => '2022',
            'foreign_purchase_price' => 7000,
            'sale_price' => 10000,
            'status' => Car::STATUS_AVAILABLE,
        ]);
        $car2 = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Kia',
            'model' => 'Sportage',
            'manufacture_year' => '2023',
            'foreign_purchase_price' => 9000,
            'sale_price' => 15000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        // 5. Create Orders
        $this->order1 = Order::create([
            'order_number' => 'ORD-AG1-01',
            'customer_id' => $this->customer1->id,
            'car_id' => $car1->id,
            'agent_id' => $this->agent1->id,
            'status' => Order::STATUS_SHIPPING,
            'remaining_amount' => 10000,
        ]);
        $this->order2 = Order::create([
            'order_number' => 'ORD-AG2-02',
            'customer_id' => $this->customer2->id,
            'car_id' => $car2->id,
            'agent_id' => $this->agent2->id,
            'status' => Order::STATUS_SHIPPING,
            'remaining_amount' => 15000,
        ]);

        // 6. Create Customer Payments
        $this->payment1 = CustomerPayment::create([
            'order_id' => $this->order1->id,
            'customer_id' => $this->customer1->id,
            'amount' => 2000,
            'received_by' => $this->user1->id,
            'agent_id' => $this->agent1->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Payment to Agent 1',
            'created_by' => $this->user1->id,
        ]);
        $this->payment2 = CustomerPayment::create([
            'order_id' => $this->order2->id,
            'customer_id' => $this->customer2->id,
            'amount' => 3000,
            'received_by' => $this->user2->id,
            'agent_id' => $this->agent2->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Payment to Agent 2',
            'created_by' => $this->user2->id,
        ]);

        // 7. Create Agent Transactions
        $this->agentTx1 = AgentTransaction::create([
            'agent_id' => $this->agent1->id,
            'direction' => AgentTransaction::DIRECTION_OUT,
            'amount' => 2000,
            'payment_id' => $this->payment1->id,
            'previous_balence' => 0,
            'current_balence' => -2000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Debt from payment',
            'created_by' => $this->user1->id,
        ]);
        $this->agentTx2 = AgentTransaction::create([
            'agent_id' => $this->agent2->id,
            'direction' => AgentTransaction::DIRECTION_OUT,
            'amount' => 3000,
            'payment_id' => $this->payment2->id,
            'previous_balence' => 0,
            'current_balence' => -3000,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Debt from payment',
            'created_by' => $this->user2->id,
        ]);

        // 8. Create Treasury Transactions
        $this->treasuryTx1 = TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 2000,
            'previous_balence' => 0,
            'current_balence' => 2000,
            'source_type' => TreasuryTransaction::SOURCE_AGENT_REMITTANCE,
            'source_id' => $this->agentTx1->id,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'created_by' => $this->user1->id,
        ]);
        $this->treasuryTx2 = TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 3000,
            'previous_balence' => 2000,
            'current_balence' => 5000,
            'source_type' => TreasuryTransaction::SOURCE_AGENT_REMITTANCE,
            'source_id' => $this->agentTx2->id,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'created_by' => $this->user2->id,
        ]);
    }

    public function test_agent_can_only_see_their_own_customers(): void
    {
        Sanctum::actingAs($this->user1);

        // List customers
        $response = $this->getJson('/api/customers');
        $response->assertOk();
        
        $customerIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->customer1->id, $customerIds);
        $this->assertNotContains($this->customer2->id, $customerIds);

        // View details
        $this->getJson("/api/customers/{$this->customer1->id}")->assertOk();
        $this->getJson("/api/customers/{$this->customer2->id}")->assertStatus(403);

        // Update customer
        $this->putJson("/api/customers/{$this->customer1->id}", [
            'name' => 'Updated Customer 1',
        ])->assertOk();
        $this->putJson("/api/customers/{$this->customer2->id}", [
            'name' => 'Updated Customer 2',
        ])->assertStatus(403);
    }

    public function test_agent_can_only_see_their_own_orders(): void
    {
        Sanctum::actingAs($this->user1);

        // List orders
        $response = $this->getJson('/api/orders');
        $response->assertOk();

        $orderIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->order1->id, $orderIds);
        $this->assertNotContains($this->order2->id, $orderIds);

        // View order details
        $this->getJson("/api/orders/{$this->order1->id}")->assertOk();
        $this->getJson("/api/orders/{$this->order2->id}")->assertStatus(403);
    }

    public function test_agent_can_only_see_their_own_customer_payments(): void
    {
        Sanctum::actingAs($this->user1);

        // List customer payments
        $response = $this->getJson('/api/customer-payments');
        $response->assertOk();

        $paymentIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->payment1->id, $paymentIds);
        $this->assertNotContains($this->payment2->id, $paymentIds);

        // View details
        $this->getJson("/api/customer-payments/{$this->payment1->id}")->assertOk();
        $this->getJson("/api/customer-payments/{$this->payment2->id}")->assertStatus(403);
    }

    public function test_agent_transactions_endpoint_returns_only_their_own_agent_customer_payments(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/agent-transactions');
        $response->assertOk();

        $paymentIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->payment1->id, $paymentIds);
        $this->assertNotContains($this->payment2->id, $paymentIds);

        // Single-record details still use the real ledger model.
        $this->getJson("/api/agent-transactions/{$this->agentTx1->id}")->assertOk();
        $this->getJson("/api/agent-transactions/{$this->agentTx2->id}")->assertStatus(403);
    }

    public function test_agent_transactions_endpoint_can_filter_agent_customer_payments_by_agent(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@zaki.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/agent-transactions?agent_id=' . $this->agent2->id);
        $response->assertOk();

        $paymentIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->payment2->id, $paymentIds);
        $this->assertNotContains($this->payment1->id, $paymentIds);
    }

    public function test_agent_can_only_see_their_own_treasury_transfers(): void
    {
        Sanctum::actingAs($this->user1);

        // List treasury transactions (requires bypassing the full treasury.view checks)
        $response = $this->getJson('/api/tresury/summary');
        $response->assertOk();

        $txIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->treasuryTx1->id, $txIds);
        $this->assertNotContains($this->treasuryTx2->id, $txIds);
    }

    public function test_dashboard_metrics_are_scoped_for_agent(): void
    {
        // Grant temporary dashboard.view permission to agent role to test access
        $role = \Spatie\Permission\Models\Role::findByName('agent', 'api');
        $role->givePermissionTo('dashboard.view');

        Order::create([
            'order_number' => 'ORD-AG1-REAL',
            'customer_id' => $this->customer1->id,
            'car_id' => $this->order1->car_id,
            'agent_id' => $this->agent1->id,
            'status' => Order::STATUS_SOLD,
            'remaining_amount' => 10000,
        ]);

        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/dashboard');
        $response->assertOk();

        // The first order per car is not a real sale, so only the later
        // order for Agent 1 is counted.
        $response->assertJsonPath('data.orders_count', 1);
        $response->assertJsonPath('data.cars_count', 1);
        $response->assertJsonPath('data.total_sales', 10000);
        $response->assertJsonPath('data.customers_count', 1);
        $response->assertJsonPath('data.agents_count', 1);
    }
}
