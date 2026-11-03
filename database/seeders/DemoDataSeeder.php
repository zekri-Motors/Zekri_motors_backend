<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\Batch;
use App\Models\Car;
use App\Models\CarExpense;
use App\Models\ContainerOpener;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ServiceProviderModel;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private User $admin;

    private float $treasuryBalance = 0;

    /**
     * Seed realistic demo data without deleting existing records.
     */
    // public function run(): void
    // {
    //     // DB::transaction(function () {
    //     //     $this->admin = $this->user(
    //     //         'admin@zaki.com',
    //     //         'Admin User',
    //     //         '0550000001',
    //     //         'admin'
    //     //     );

    //         //$agents = $this->seedAgents();
    //         //$customers = $this->seedCustomers($agents);
    //         //$suppliers = $this->seedSuppliers();
    //         //$containerOpeners = $this->seedContainerOpeners();
    //         //$serviceProviders = $this->seedServiceProviders();
    //         //$batches = $this->seedBatches($suppliers);
    //         //$cars = $this->seedCars($batches, $suppliers, $containerOpeners);

    //         // $this->seedCarExpenses($cars);
    //         // $this->seedDocuments($cars);
    //         // $this->seedSupplierPayments($batches);
    //         // $orders = $this->seedOrders($customers, $agents, $cars);
    //         // $this->seedCustomerPayments($orders);
    //         // $this->seedExpenses($orders, $cars, $serviceProviders);
    //         // $this->seedInvoices($orders);
    //         // $this->seedSettings();
    //     });
    // }

    // private function user(string $email, string $name, string $phone, string $role): User
    // {
    //     $user = User::query()->updateOrCreate(
    //         ['email' => $email],
    //         [
    //             'name' => $name,
    //             'phone' => $phone,
    //             'password' => Hash::make('12345678'),
    //             'is_active' => true,
    //         ]
    //     );

    //     $user->forceFill(['email_verified_at' => now()])->save();

    //     if (! $user->hasRole($role)) {
    //         $user->assignRole($role);
    //     }

    //     return $user;
    // }

    /**
     * @return array<int, Agent>
     */
    // private function seedAgents(): array
    // {
    //     $rows = [
    //         ['agent1@zaki.com', 'Karim Haddad', '0551001001', 'Algiers center sales agent'],
    //         ['agent2@zaki.com', 'Nadia Mansouri', '0551001002', 'Oran west region agent'],
    //         ['agent3@zaki.com', 'Sami Benali', '0551001003', 'Constantine east region agent'],
    //     ];

    //     return array_map(function (array $row) {
    //         [$email, $name, $phone, $notes] = $row;
    //         $user = $this->user($email, $name, $phone, 'agent');

    //         return Agent::query()->updateOrCreate(
    //             ['email' => $email],
    //             [
    //                 'user_id' => $user->id,
    //                 'name' => $name,
    //                 'phone' => $phone,
    //                 'address' => fake()->address(),
    //                 'notes' => $notes,
    //             ]
    //         );
    //     }, $rows);
    // }

    /**
     * @param array<int, Agent> $agents
     * @return array<int, Customer>
     */
    // private function seedCustomers(array $agents): array
    // {
    //     $rows = [
    //         ['customer1@zaki.com', 'Yacine Amrani', '0662002001', 'ID-100001', 'AB123456', 0],
    //         ['customer2@zaki.com', 'Meriem Saidi', '0662002002', 'ID-100002', 'AB123457', 1],
    //         ['customer3@zaki.com', 'Walid Toumi', '0662002003', 'ID-100003', 'AB123458', 2],
    //         ['customer4@zaki.com', 'Amina Cherif', '0662002004', 'ID-100004', 'AB123459', 0],
    //         ['customer5@zaki.com', 'Omar Belkacem', '0662002005', 'ID-100005', 'AB123460', null],
    //         ['customer6@zaki.com', 'Lina Kaced', '0662002006', 'ID-100006', 'AB123461', 1],
    //     ];

    //     return array_map(function (array $row) use ($agents) {
    //         [$email, $name, $phone, $nationalId, $passportNo, $agentIndex] = $row;
    //         $agent = $agentIndex === null ? null : $agents[$agentIndex];

    //         return Customer::query()->updateOrCreate(
    //             ['email' => $email],
    //             [
                    
    //                 'agent_id' => $agent?->id,
    //                 'name' => $name,
    //                 'phone' => $phone,
    //                 'national_id' => $nationalId,
    //                 'passport_no' => $passportNo,
    //                 'address' => fake()->address(),
    //             ]
    //         );
    //     }, $rows);
    // }

    /**
     * @return array<int, Supplier>
     */
    // private function seedSuppliers(): array
    // {
    //     $rows = [
    //         ['Tokyo Auto Export', '+81300010001', 'sales@tokyo-auto.test'],
    //         ['Dubai Motors Trading', '+971500010002', 'orders@dubai-motors.test'],
    //         ['Hamburg Vehicle GmbH', '+49400010003', 'export@hamburg-vehicle.test'],
    //         ['Seoul Car Auction', '+8220010004', 'auction@seoul-car.test'],
    //     ];

    //     return array_map(fn (array $row) => Supplier::query()->updateOrCreate(
    //         ['email' => $row[2]],
    //         [
    //             'name' => $row[0],
    //             'phone' => $row[1],
    //             'address' => fake()->address(),
    //             'notes' => 'Demo supplier for imported vehicles.',
    //         ]
    //     ), $rows);
    // }

    // /**
    //  * @return array<int, ContainerOpener>
    //  */
    private function seedContainerOpeners(): array
    {
        $rows = [
            ['Port Clear DZ', '0553003001', 'clearance@portclear.test', 'NIF-CL-001'],
            ['Maghreb Transit Services', '0553003002', 'ops@maghreb-transit.test', 'NIF-CL-002'],
            ['Atlas Customs Broker', '0553003003', 'desk@atlas-customs.test', 'NIF-CL-003'],
        ];

        return array_map(fn (array $row) => ContainerOpener::query()->updateOrCreate(
            ['email' => $row[2]],
            [
                'name' => $row[0],
                'phone' => $row[1],
                'address' => fake()->address(),
                'nif' => $row[3],
                'notes' => 'Demo customs and container opening contact.',
            ]
        ), $rows);
    }

    /**
     * @return array<int, ServiceProviderModel>
     */
    // private function seedServiceProviders(): array
    // {
    //     $rows = [
    //         ['Mediterranean Shipping Line', 'shipping', '0554004001', 'shipping@demo.test'],
    //         ['Rapid Tow Transport', 'transport', '0554004002', 'tow@demo.test'],
    //         ['Clean Auto Detailing', 'detailing', '0554004003', 'clean@demo.test'],
    //         ['Workshop Pro Repair', 'repair', '0554004004', 'repair@demo.test'],
    //     ];

    //     return array_map(fn (array $row) => ServiceProviderModel::query()->updateOrCreate(
    //         ['email' => $row[3]],
    //         [
    //             'name' => $row[0],
    //             'provider_type' => $row[1],
    //             'phone' => $row[2],
    //             'address' => fake()->address(),
    //             'notes' => 'Demo service provider.',
    //         ]
    //     ), $rows);
    // }

    /**
     * @param array<int, Supplier> $suppliers
     * @return array<int, Batch>
     */
    // private function seedBatches(array $suppliers): array
    // {
    //     $statuses = [
    //         Batch::STATUS_PARTIAL,
    //         Batch::STATUS_FULLY_PAID
    //     ];

    //     $batches = [];
    //     foreach (range(1, 6) as $index) {
    //         $supplier = $suppliers[($index - 1) % count($suppliers)];
    //         $batches[] = Batch::query()->updateOrCreate(
    //             [
    //                 'supplier_id' => $supplier->id,
    //                 'purchase_date' => now()->subDays(120 - ($index * 12))->toDateString(),
    //                 'total_paid_amount_foreign' => 25000 + ($index * 6500),
    //                 'exchange_rate' => 135 + ($index * 1.75),
    //                 'status' => $statuses[$index % count($statuses)],
    //                 'notes' => 'Demo import batch '.$index,
    //             ]
    //         );
    //     }

    //     return $batches;
    // }

    /**
     * @param array<int, Batch> $batches
     * @param array<int, Supplier> $suppliers
     * @param array<int, ContainerOpener> $containerOpeners
     * @return array<int, Car>
     */
    // private function seedCars(array $batches, array $suppliers, array $containerOpeners): array
    // {
    //     $rows = [
    //         ['Toyota', 'Corolla', 'Hybrid Active', 2022, 'White', 12200, 15800, Car::STATUS_AVAILABLE],
    //         ['Hyundai', 'Tucson', 'Comfort', 2021, 'Gray', 16800, 21400, Car::STATUS_AVAILABLE],
    //         ['Kia', 'Sportage', 'GT Line', 2023, 'Black', 19700, 25200, Car::STATUS_SHIPPING],
    //         ['Volkswagen', 'Golf', 'Life', 2020, 'Blue', 14200, 18400, Car::STATUS_AVAILABLE],
    //         ['Renault', 'Clio', 'Intens', 2022, 'Red', 9800, 13200, Car::STATUS_DELIVERED],
    //         ['Mercedes-Benz', 'C200', 'Avantgarde', 2021, 'Silver', 31500, 39800, Car::STATUS_SOLD],
    //         ['BMW', 'X1', 'xDrive', 2022, 'White', 28600, 36500, Car::STATUS_SOLD],
    //         ['Peugeot', '3008', 'Allure', 2023, 'Green', 22100, 28900, Car::STATUS_AVAILABLE],
    //         ['Nissan', 'Qashqai', 'Tekna', 2020, 'Black', 15100, 19900, Car::STATUS_SHIPPING],
    //         ['Audi', 'A3', 'S Line', 2021, 'Gray', 23600, 30600, Car::STATUS_SOLD],
    //         ['Skoda', 'Octavia', 'Style', 2022, 'Blue', 17300, 22800, Car::STATUS_AVAILABLE],
    //         ['Ford', 'Kuga', 'Titanium', 2021, 'White', 18100, 23900, Car::STATUS_AVAILABLE],
    //     ];

    //     $cars = [];
    //     foreach ($rows as $index => $row) {
    //         [$brand, $model, $finition, $year, $color, $purchasePrice, $salePrice, $status] = $row;
    //         $batch = $batches[$index % count($batches)];
    //         $supplier = $suppliers[$index % count($suppliers)];
    //         $opener = $containerOpeners[$index % count($containerOpeners)];
    //         $vin = 'DEMOZAKI'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT);

    //         $cars[] = Car::query()->updateOrCreate(
    //             ['vin' => $vin],
    //             [
    //                 'batch_id' => $batch->id,
    //                 'supplier_id' => $supplier->id,
    //                 'container_opener_id' => $opener->id,
    //                 'brand' => $brand,
    //                 'model' => $model,
    //                 'finition' => $finition,
    //                 'manufacture_year' => $year,
    //                 'color' => $color,
    //                 'foreign_purchase_price' => $purchasePrice,
    //                 'sale_price' => $salePrice,
    //                 'tracking_number' => 'TRK-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
    //                 'container_no' => 'CONT'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
    //                 'shipping_date' => now()->subDays(80 - $index)->toDateString(),
    //                 'arrival_date' => now()->subDays(45 - $index)->toDateString(),
    //                 'delivery_date' => in_array($status, [Car::STATUS_DELIVERED, Car::STATUS_SOLD], true)
    //                     ? now()->subDays(12 - min($index, 10))->toDateString()
    //                     : null,
    //                 'status' => $status,
    //                 'notes' => 'Demo car record.',
    //             ]
    //         );
    //     }

    //     foreach ($batches as $batch) {
    //         $batch->forceFill(['cars_count' => $batch->cars()->count()])->save();
    //     }

    //     return $cars;
    // }

    /**
     * @param array<int, Car> $cars
     */
    // private function seedCarExpenses(array $cars): void
    // {
    //     $types = ['Customs duty', 'Port storage', 'Internal transport'];

    //     foreach ($cars as $index => $car) {
    //         foreach ($types as $typeIndex => $type) {
    //             CarExpense::query()->updateOrCreate(
    //                 ['car_id' => $car->id, 'expense_type' => $type],
    //                 [
    //                     'foreign_amount' => 120 + ($index * 15) + ($typeIndex * 25),
    //                     'local_amount' => 18000 + ($index * 1200) + ($typeIndex * 2500),
    //                     'notes' => 'Demo '.$type.' cost.',
    //                 ]
    //             );
    //         }
    //     }
    // }

    /**
     * @param array<int, Car> $cars
     */
    // private function seedDocuments(array $cars): void
    // {
    //     foreach ($cars as $car) {
    //         foreach (['Purchase Invoice', 'Customs Document'] as $title) {
    //             Document::query()->updateOrCreate(
    //                 ['car_id' => $car->id, 'title' => $title],
    //                 [
    //                     'file_path' => 'demo/documents/car-'.$car->id.'-'.str($title)->slug().'.pdf',
    //                     'created_at' => now()->subDays(10),
    //                 ]
    //             );
    //         }
    //     }
    // }

    /**
     * @param array<int, Batch> $batches
     */
    // private function seedSupplierPayments(array $batches): void
    // {
    //     foreach ($batches as $index => $batch) {
    //         $amountForeign = (float) $batch->total_paid_amount_foreign * 0.55;
    //         $amountLocal = $amountForeign * (float) $batch->exchange_rate;

    //         $payment = SupplierPayment::query()->updateOrCreate(
    //             ['batch_id' => $batch->id, 'supplier_id' => $batch->supplier_id, 'payment_date' => $batch->purchase_date],
    //             [
    //                 'amount_foreign' => $amountForeign,
    //                 'exchange_rate' => $batch->exchange_rate,
    //                 'amount_local' => $amountLocal,
    //                 'attachment' => 'demo/payments/supplier-'.$batch->id.'.pdf',
    //                 'notes' => 'Demo supplier payment.',
    //                 'created_by' => $this->admin->id,
    //             ]
    //         );

    //         $this->treasury(
    //             TreasuryTransaction::DIRECTION_OUT,
    //             $amountLocal,
    //             TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT,
    //             $payment->id,
    //             now()->subDays(60 - $index)->toDateString(),
    //             'Supplier payment for '.$batch->supplier->name.' (Batch ID: '.$batch->id.')'
    //         );
    //     }
    // }

    /**
     * @param array<int, Customer> $customers
     * @param array<int, Agent> $agents
     * @param array<int, Car> $cars
     * @return array<int, Order>
     */
    // private function seedOrders(array $customers, array $agents, array $cars): array
    // {
    //     $statuses = [
    //         Order::STATUS_AVAILABLE,
    //         Order::STATUS_SHIPPING,
    //         Order::STATUS_IN_SHOW_ROOM,
    //         Order::STATUS_SOLD,
    //         Order::STATUS_DELIVERED,
    //     ];
    //     $orderCars = array_slice($cars, 4, 6);

    //     $orders = [];
    //     foreach ($orderCars as $index => $car) {
    //         $customer = $customers[$index % count($customers)];
    //         $agent = $customer->agent_id ? $agents[array_search($customer->agent_id, array_column($agents, 'id'), true)] ?? null : null;
    //         $status = $statuses[$index % count($statuses)];
    //         $paid = round((float) $car->sale_price * (0.25 + ($index * 0.08)), 2);

    //         $orders[] = Order::query()->updateOrCreate(
    //             ['order_number' => 'ORD-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
    //             [
    //                 'customer_id' => $customer->id,
    //                 'car_id' => $car->id,
    //                 'agent_id' => $agent?->id,
    //                 'status' => $status,
    //                 'purchase_date' => now()->subDays(35 - ($index * 3))->toDateString(),
    //                 'shipping_date' => now()->subDays(30 - ($index * 3))->toDateString(),
    //                 'arrival_date' => $index >= 2 ? now()->subDays(15 - $index)->toDateString() : null,
    //                 'delivery_date' => $status === Order::STATUS_DELIVERED ? now()->subDays(3)->toDateString() : null,
    //                 'paid_amount' => $paid,
    //                 'remaining_amount' => max((float) $car->sale_price - $paid, 0),
    //                 'notes' => 'Demo customer order.',
    //                 'created_by' => $this->admin->id,
    //             ]
    //         );

    //         $car->forceFill([
    //             'status' => $status === Order::STATUS_DELIVERED ? Car::STATUS_SOLD : Car::STATUS_IN_SHOW_ROOM,
    //         ])->save();
    //     }

    //     return $orders;
    // }

    /**
     * @param array<int, Order> $orders
     */
    // private function seedCustomerPayments(array $orders): void
    // {
    //     foreach ($orders as $index => $order) {
    //         $payments = [
    //             [round((float) $order->paid_amount * 0.5, 2), 'company', null, now()->subDays(20 - $index)->toDateString()],
    //             [round((float) $order->paid_amount * 0.3, 2), 'agent', $order->agent_id, now()->subDays(15 - $index)->toDateString()],
    //             [round((float) $order->paid_amount * 0.2, 2), 'company', null, now()->subDays(10 - $index)->toDateString()],
    //         ];

    //         foreach ($payments as $paymentData) {
    //             [$amount, $receivedBy, $agentId, $date] = $paymentData;
    //             $payment = CustomerPayment::query()->updateOrCreate(
    //                 ['order_id' => $order->id, 'amount' => $amount, 'payment_date' => $date],
    //                 [
    //                     'customer_id' => $order->customer_id,
    //                     'received_by' => $this->admin->id,
    //                     'agent_id' => $agentId,
    //                     'attachment' => 'demo/payments/customer-'.$order->id.'-'.$amount.'.pdf',
    //                     'notes' => 'Demo customer payment.',
    //                     'created_by' => $this->admin->id,
    //                 ]
    //             );
    //         }

    //         $order->recalculateBalance();
    //     }
    // }

    // private function agentTransaction(int $agentId, CustomerPayment $payment, string $date): AgentTransaction
    // {
    //     $lastBalance = (float) AgentTransaction::query()
    //         ->where('agent_id', $agentId)
    //         ->latest('transaction_date')
    //         ->latest('id')
    //         ->value('current_balence');

    //     $currentBalance = $lastBalance + (float) $payment->amount;

    //     $agentTransaction = AgentTransaction::query()->updateOrCreate(
    //         ['agent_id' => $agentId, 'payment_id' => $payment->id],
    //         [
    //             'direction' => AgentTransaction::DIRECTION_IN,
    //             'amount' => $payment->amount,
    //             'previous_balence' => $lastBalance,
    //             'current_balence' => $currentBalance,
    //             'transaction_date' => $date,
    //             'attachment' => 'demo/agents/payment-'.$payment->id.'.pdf',
    //             'notes' => 'Payment collected by agent.',
    //             'created_by' => $this->admin->id,
    //         ]
    //     );

    //     $treasuryTransaction = $this->treasury(
    //         TreasuryTransaction::DIRECTION_IN,
    //         (float) $payment->amount,
    //         TreasuryTransaction::SOURCE_AGENT_REMITTANCE,
    //         $agentTransaction->id,
    //         $date,
    //         'Agent remittance for payment '.$payment->id
    //     );

    //     $agentTransaction->forceFill(['transaction_id' => $treasuryTransaction->id])->save();

    //     return $agentTransaction;
    // }

    /**
     * @param array<int, Order> $orders
     * @param array<int, Car> $cars
     * @param array<int, ServiceProviderModel> $serviceProviders
     */
    // private function seedExpenses(array $orders, array $cars, array $serviceProviders): void
    // {
    //     foreach ($orders as $index => $order) {
    //         $provider = $serviceProviders[$index % count($serviceProviders)];
    //         $expenseDate = now()->subDays(9 - $index)->toDateString();
    //         $expense = Expense::query()->updateOrCreate(
    //             ['order_id' => $order->id, 'expense_type' => 'Delivery paperwork'],
    //             [
    //                 'car_id' => $order->car_id,
    //                 'service_provider_id' => $provider->id,
    //                 'amount' => 8500 + ($index * 1300),
    //                 'attachment' => 'demo/expenses/order-'.$order->id.'.pdf',
    //                 'expense_date' => $expenseDate,
    //                 'notes' => 'Demo order expense.',
    //                 'created_by' => $this->admin->id,
    //             ]
    //         );

    //         $this->treasury(
    //             TreasuryTransaction::DIRECTION_OUT,
    //             (float) $expense->amount,
    //             TreasuryTransaction::SOURCE_EXPENSE,
    //             $expense->id,
    //             $expenseDate,
    //             'Expense for order '.$order->order_number
    //         );
    //     }

    //     foreach (array_slice($cars, 0, 5) as $index => $car) {
    //         Expense::query()->updateOrCreate(
    //             ['car_id' => $car->id, 'expense_type' => 'Showroom preparation'],
    //             [
    //                 'order_id' => null,
    //                 'service_provider_id' => $serviceProviders[($index + 1) % count($serviceProviders)]->id,
    //                 'amount' => 6000 + ($index * 900),
    //                 'attachment' => 'demo/expenses/car-'.$car->id.'.pdf',
    //                 'expense_date' => now()->subDays(7 - $index)->toDateString(),
    //                 'notes' => 'Demo car preparation expense.',
    //                 'created_by' => $this->admin->id,
    //             ]
    //         );
    //     }
    // }

    /**
     * @param array<int, Order> $orders
     */
    // private function seedInvoices(array $orders): void
    // {
    //     foreach ($orders as $index => $order) {
    //         $order->refresh();
    //         $status = match (true) {
    //             (float) $order->paid_amount <= 0 => Invoice::STATUS_UNPAID,
    //             (float) $order->remaining_amount <= 0 => Invoice::STATUS_PAID,
    //             default => Invoice::STATUS_PARTIAL,
    //         };

    //         Invoice::query()->updateOrCreate(
    //             ['invoice_number' => 'INV-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
    //             [
    //                 'order_id' => $order->id,
    //                 'total_amount' => $order->car->sale_price,
    //                 'paid_amount' => $order->paid_amount,
    //                 'remaining_amount' => $order->remaining_amount,
    //                 'status' => $status,
    //             ]
    //         );
    //     }
    // }

    private function seedSettings(): void
    {
        $settings = [
            'company_name' => 'Zaki Auto',
            'company_phone' => '+213550000000',
            'company_email' => 'contact@zaki-auto.test',
            'default_currency' => 'DZD',
            'invoice_prefix' => 'INV',
            'order_prefix' => 'ORD',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    // private function treasury(
    //     string $direction,
    //     float $amount,
    //     string $sourceType,
    //     int $sourceId,
    //     string $date,
    //     string $notes
    // ): TreasuryTransaction {
    //     $existing = TreasuryTransaction::query()
    //         ->where('source_type', $sourceType)
    //         ->where('source_id', $sourceId)
    //         ->first();

    //     $previous = $existing ? (float) $existing->previous_balence : $this->treasuryBalance;
    //     $current = $direction === TreasuryTransaction::DIRECTION_IN
    //         ? $previous + $amount
    //         : $previous - $amount;

    //     $transaction = TreasuryTransaction::query()->updateOrCreate(
    //         ['source_type' => $sourceType, 'source_id' => $sourceId],
    //         [
    //             'direction' => $direction,
    //             'amount' => $amount,
    //             'previous_balence' => $previous,
    //             'current_balence' => $current,
    //             'transaction_date' => $date,
    //             'notes' => $notes,
    //             'created_by' => $this->admin->id,
    //         ]
    //     );

    //     $this->treasuryBalance = $current;

    //     return $transaction;
    // }
}