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

class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Car $car;
    private Order $order;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::where('email', 'superadmin@zaki.com')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'phone' => '0555555555',
            'email' => 'supplier@test.com',
        ]);

        $batch = Batch::create([
            'supplier_id' => $supplier->id,
            'exchange_rate' => 1.0,
            'status' => 'partial',
        ]);

        $this->car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => '2021',
            'foreign_purchase_price' => 8000,
            'sale_price' => 12000,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '0566666666',
            'email' => 'customer@test.com',
            'national_id' => '1234567890123456',
            'passport_no' => 'P1234567',
        ]);

        $this->agent = Agent::create([
            'name' => 'Test Agent',
            'phone' => '0577777777',
            'email' => 'agent@test.com',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-TEST-01',
            'customer_id' => $this->customer->id,
            'car_id' => $this->car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);
        
        $this->order->recalculateBalance();
    }

    public function test_create_customer_payment_sets_received_by_to_current_user(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/customer-payments', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'notes' => 'Direct payment to company',
        ]);

        $response->assertCreated();

        $payment = CustomerPayment::first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->user->id, $payment->received_by);
        $this->assertEquals($this->user->id, $payment->created_by);
        $this->assertFalse($payment->wasCollectedByAgent());

        // Assert direct company payment posts a Treasury movement
        $this->assertDatabaseHas('treasury_transactions', [
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 5000,
        ]);

        $this->assertDatabaseMissing('treasury_transactions', [
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
            'direction' => TreasuryTransaction::DIRECTION_OUT,
        ]);
    }

    public function test_create_customer_payment_with_agent_creates_agent_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/customer-payments', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 4000,
            'agent_id' => $this->agent->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Payment collected by agent',
        ]);

        $response->assertCreated();

        $payment = CustomerPayment::first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->user->id, $payment->received_by);
        $this->assertEquals($this->user->id, $payment->created_by);
        $this->assertTrue($payment->wasCollectedByAgent());

        // Assert agent payment posts an Agent Transaction (as agent debt)
        $this->assertDatabaseHas('agent_transactions', [
            'agent_id' => $this->agent->id,
            'direction' => AgentTransaction::DIRECTION_OUT,
            'payment_id' => $payment->id,
            'amount' => 4000,
        ]);

        // Treasury should NOT have been updated yet since it's with the agent
        $this->assertDatabaseMissing('treasury_transactions', [
            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
            'source_id' => $payment->id,
        ]);
    }

    public function test_customer_payment_treasury_transfer_statuses(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Direct payment to company by super-admin (should be automatically transferred and approved)
        $directResponse = $this->postJson('/api/customer-payments', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'notes' => 'Direct payment to company',
        ]);
        $directResponse->assertCreated();
        $directResponse->assertJsonPath('data.is_transferred_to_treasury', true);
        $directResponse->assertJsonPath('data.general_treasury_transfer_status', 'approved');

        $directPaymentId = $directResponse->json('data.id');

        // 2. Agent payment (not remitted, is_transferred_to_treasury: false, general treasury status: null)
        $agentResponse = $this->postJson('/api/customer-payments', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 4000,
            'agent_id' => $this->agent->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Agent collected payment',
        ]);
        $agentResponse->assertCreated();
        $agentResponse->assertJsonPath('data.is_transferred_to_treasury', false);
        $agentResponse->assertJsonPath('data.general_treasury_transfer_status', null);

        $agentPaymentId = $agentResponse->json('data.id');

        // Let's verify the index endpoint returns the new fields too
        $indexResponse = $this->getJson('/api/customer-payments');
        $indexResponse->assertOk();
        
        $directPaymentData = collect($indexResponse->json('data'))->firstWhere('id', $directPaymentId);
        $this->assertTrue($directPaymentData['is_transferred_to_treasury']);
        $this->assertEquals('approved', $directPaymentData['general_treasury_transfer_status']);

        $agentPaymentData = collect($indexResponse->json('data'))->firstWhere('id', $agentPaymentId);
        $this->assertFalse($agentPaymentData['is_transferred_to_treasury']);
        $this->assertNull($agentPaymentData['general_treasury_transfer_status']);

        // 3. Staging the direct payment again should be rejected with 422 because it's already approved.
        $stageResponse = $this->postJson("/api/customer-payments/{$directPaymentId}/transfer-to-treasury");
        $stageResponse->assertStatus(422);
        $stageResponse->assertJsonFragment([
            'message' => 'تم تحويل هذه الدفعة واعتمادها في الخزينة العامة بالفعل'
        ]);

        $this->assertDatabaseHas('customer_payments', [
            'id' => $directPaymentId,
            'approved_by' => $this->user->id,
        ]);

        // 4. Test the flow for a regular user (pending -> approved)
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'regular@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $regularPayment = CustomerPayment::create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 3000,
            'received_by' => $regularUser->id,
            'payment_date' => now()->toDateString(),
            'notes' => 'Received by regular user',
            'created_by' => $this->user->id,
        ]);

        // Stage the regular payment (should become pending)
        $stageRegular = $this->postJson("/api/customer-payments/{$regularPayment->id}/transfer-to-treasury");
        $stageRegular->assertCreated();
        $stageRegular->assertJsonPath('data.general_treasury_transfer_status', 'pending');

        // Approve the transfer (should become approved)
        $approveRegular = $this->postJson("/api/customer-payments/{$regularPayment->id}/approve-treasury-transfer");
        $approveRegular->assertOk();
        $approveRegular->assertJsonPath('data.general_treasury_transfer_status', 'approved');
        $approveRegular->assertJsonPath('data.approved_by', $this->user->name);
        $approveRegular->assertJsonPath('data.approver.name', $this->user->name);
        $this->assertNotNull($approveRegular->json('data.approved_at'));
    }

    public function test_treasury_summary_displays_customer_and_supplier_names(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create a customer payment that goes to the treasury
        $this->postJson('/api/customer-payments', [
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'notes' => 'Customer payment test',
        ])->assertCreated();

        // 2. Create a supplier payment
        $supplier = Supplier::create([
            'name' => 'Fancy Supplier',
            'phone' => '0599999999',
            'email' => 'fancy@test.com',
        ]);
        $batch = new Batch([
            'supplier_id' => $supplier->id,
            'status' => 'partial',
        ]);
        $batch->forceFill([
            'total_cost_foreign' => 10000.00,
            'exchange_rate' => 1.0,
        ]);
        $batch->save();
        
        // Let's seed initial treasury balance to afford the supplier payment
        TreasuryTransaction::create([
            'direction' => TreasuryTransaction::DIRECTION_IN,
            'amount' => 100000,
            'previous_balence' => 0,
            'current_balence' => 100000,
            'source_type' => 'manual_deposit',
            'source_id' => 0,
            'transaction_date' => now()->toDateString(),
            'status' => TreasuryTransaction::STATUS_APPROVED,
            'notes' => 'Top up',
            'created_by' => $this->user->id,
        ]);

        $this->postJson('/api/supplier-payments', [
            'batch_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'amount_foreign' => 2000,
            'exchange_rate' => 1.0,
            'amount_local' => 2000,
            'payment_date' => now()->toDateString(),
            'notes' => 'Supplier payment test',
        ])->assertCreated();

        // 3. Request treasury summary index
        $response = $this->getJson('/api/tresury/summary');
        $response->assertOk();

        // Check customer payment transaction contains the customer name
        $customerTx = collect($response->json('data'))->firstWhere('source_type', TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT);
        $this->assertNotNull($customerTx);
        $this->assertEquals('Test Customer', $customerTx['customer_name']);
        $this->assertNull($customerTx['supplier_name']);

        // Check supplier payment transaction contains the supplier name
        $supplierTx = collect($response->json('data'))->firstWhere('source_type', TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT);
        $this->assertNotNull($supplierTx);
        $this->assertEquals('Fancy Supplier', $supplierTx['supplier_name']);
        $this->assertNull($supplierTx['customer_name']);
    }
}
