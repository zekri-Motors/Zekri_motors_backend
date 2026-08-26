<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Car;
use App\Models\ContainerOpener;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BatchImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private ContainerOpener $containerOpener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::where('email', 'superadmin@zaki.com')->first();

        $this->supplier = Supplier::create([
            'name' => 'Tokyo Auto Export',
            'phone' => '+81300010001',
            'email' => 'sales@tokyo-auto.test',
            'address' => 'Tokyo, Japan',
        ]);

        $this->containerOpener = ContainerOpener::create([
            'name' => 'Port Clear DZ',
            'phone' => '0553003001',
            'email' => 'clearance@portclear.test',
            'address' => 'Algiers Port',
            'nif' => 'NIF-CL-001',
        ]);
    }

    public function test_import_batch_and_cars_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $file = $this->spreadsheetUpload([
            [
                'Toyota',
                'Camry',
                'GLE',
                2023,
                'Silver',
                'VIN12345678901234',
                15000,
                'TRACK123',
                'Ahmad Zaki',
                'P123456',
                'N123456',
                1500,
                '2026-07-03',
            ],
        ]);

        $response = $this->postJson('/api/batches/import', [
            'supplier_id' => $this->supplier->id,
            'container_opener_id' => $this->containerOpener->id,
            'purchase_date' => '2026-07-03',
            'total_cost_foreign' => 15000,
            'notes' => 'Import test notes',
            'file' => $file,
        ]);

        @unlink($file->getRealPath());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'batch',
                'created',
                'skipped',
                'errors',
            ],
        ]);

        $this->assertEquals(1, $response->json('data.created'));
        $this->assertEquals(0, $response->json('data.skipped'));
        $this->assertCount(0, $response->json('data.errors'));
        $this->assertNotNull($response->json('data.batch.batch_number'));

        $this->assertDatabaseHas('batches', [
            'supplier_id' => $this->supplier->id,
            'batch_number' => $response->json('data.batch.batch_number'),
            'total_cost_foreign' => 15000.00,
            'notes' => 'Import test notes',
            'cars_count' => 1,
        ]);

        $car = Car::where('vin', 'VIN12345678901234')->first();
        $this->assertNotNull($car);
        $this->assertEquals($this->containerOpener->id, $car->container_opener_id);

        $this->assertEquals(1500.00, (float) $car->shipping_cost);

        $this->assertDatabaseHas('customers', [
            'name' => 'Ahmad Zaki',
            'passport_no' => 'P123456',
            'national_id' => 'N123456',
        ]);

        $customer = Customer::where('passport_no', 'P123456')->first();

        $order = Order::where('car_id', $car->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals(Order::STATUS_SHIPPING, $order->status);
        $this->assertEquals('2026-07-03', $order->purchase_date->format('Y-m-d'));
        $this->assertNotNull($order->shipping_date);
        $this->assertEquals(15000.00, $order->remaining_amount);
        $this->assertEquals($this->user->id, $order->created_by);
        $this->assertEquals(\App\Models\Car::STATUS_SHIPPING, $car->fresh()->status);
    }

    public function test_import_failure_does_not_create_batch_or_cars(): void
    {
        Sanctum::actingAs($this->user);

        $file = $this->spreadsheetUpload([
            [
                '',
                'Corolla',
                'Active',
                2022,
                'Red',
                'VINERR123456',
                12000,
                'TRACKERR',
                'Ahmad Bad',
                'P999999',
                'N999999',
                1000,
                '2026-07-03',
            ],
        ]);

        $response = $this->postJson('/api/batches/import', [
            'supplier_id' => $this->supplier->id,
            'container_opener_id' => $this->containerOpener->id,
            'purchase_date' => '2026-07-03',
            'total_cost_foreign' => 12000,
            'notes' => 'Import failure test notes',
            'file' => $file,
        ]);

        @unlink($file->getRealPath());

        $response->assertStatus(422);
        $this->assertNull($response->json('data.batch'));
        $this->assertEquals(0, $response->json('data.created'));
        $this->assertEquals(1, $response->json('data.skipped'));
        $this->assertEquals(2, $response->json('data.errors.0.row'));
        $this->assertNotEmpty($response->json('data.errors.0.errors.0'));

        $this->assertDatabaseMissing('batches', [
            'supplier_id' => $this->supplier->id,
            'notes' => 'Import failure test notes',
        ]);
        $this->assertDatabaseMissing('cars', [
            'vin' => 'VINERR123456',
        ]);
        $this->assertDatabaseMissing('customers', [
            'passport_no' => 'P999999',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_show_batch_includes_first_and_current_owner_for_each_car(): void
    {
        Sanctum::actingAs($this->user);

        $batch = Batch::create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-07-01',
            'total_cost_foreign' => 10000.00,
            'status' => Batch::STATUS_PARTIAL,
            'exchange_rate' => 1.00,
        ]);

        $car = Car::create([
            'batch_id' => $batch->id,
            'supplier_id' => $this->supplier->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'manufacture_year' => 2022,
            'foreign_purchase_price' => 10000.00,
            'sale_price' => 15000.00,
            'status' => Car::STATUS_AVAILABLE,
        ]);

        $firstOwner = Customer::create([
            'name' => 'First Batch Owner',
            'passport_no' => 'FIRST123',
            'national_id' => 'NAT-FIRST',
        ]);

        Order::create([
            'order_number' => 'ORD-BATCH-001',
            'customer_id' => $firstOwner->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_SHIPPING,
        ]);

        $currentOwner = Customer::create([
            'name' => 'Current Batch Owner',
            'passport_no' => 'CURRENT123',
            'national_id' => 'NAT-CURRENT',
        ]);

        Order::create([
            'order_number' => 'ORD-BATCH-002',
            'customer_id' => $currentOwner->id,
            'car_id' => $car->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $response = $this->getJson("/api/batches/{$batch->id}");

        $response->assertOk()
            ->assertJsonPath('data.cars.0.id', $car->id)
            ->assertJsonPath('data.cars.0.first_owner.id', $currentOwner->id)
            ->assertJsonPath('data.cars.0.first_owner.name', 'Current Batch Owner')
            ->assertJsonPath('data.cars.0.first_owner.passport_no', 'CURRENT123')
            ->assertJsonPath('data.cars.0.first_owner.national_id', 'NAT-CURRENT')
            ->assertJsonPath('data.cars.0.current_owner.id', $currentOwner->id)
            ->assertJsonPath('data.cars.0.current_owner.name', 'Current Batch Owner')
            ->assertJsonPath('data.cars.0.current_owner.passport_no', 'CURRENT123')
            ->assertJsonPath('data.cars.0.current_owner.national_id', 'NAT-CURRENT');
    }

    public function test_batch_status_transitions_automatically(): void
    {
        Sanctum::actingAs($this->user);

        $batch = Batch::create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-07-01',
            'total_cost_foreign' => 1000.00,
            'status' => Batch::STATUS_PARTIAL,
            'exchange_rate' => 1.00,
        ]);

        $this->assertEquals(Batch::STATUS_PARTIAL, $batch->status);
        $this->assertEquals(Batch::makeBatchNumber($batch->id), $batch->batch_number);

        $response1 = $this->postJson('/api/supplier-payments', [
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 400.00,
            'exchange_rate' => 135.00,
            'payment_date' => '2026-07-02',
        ]);

        $response1->assertStatus(201);
        $batch->refresh();
        $this->assertEquals(Batch::STATUS_PARTIAL, $batch->status);
        $this->assertEquals(400.00, (float) $batch->total_paid_amount_foreign);

        $response2 = $this->postJson('/api/supplier-payments', [
            'supplier_id' => $this->supplier->id,
            'amount_foreign' => 600.00,
            'exchange_rate' => 135.00,
            'payment_date' => '2026-07-03',
        ]);

        $response2->assertStatus(201);
        $batch->refresh();
        $this->assertEquals(Batch::STATUS_FULLY_PAID, $batch->status);
        $this->assertEquals(1000.00, (float) $batch->total_paid_amount_foreign);

        $payment2Id = $response2->json('payments_created.0.id');
        $this->assertNotNull($payment2Id);

        $responseDelete = $this->deleteJson("/api/supplier-payments/{$payment2Id}");
        $responseDelete->assertStatus(200);

        $batch->refresh();
        $this->assertEquals(Batch::STATUS_PARTIAL, $batch->status);
        $this->assertEquals(400.00, (float) $batch->total_paid_amount_foreign);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function spreadsheetUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'العلامة التجارية',
            'الموديل',
            'الفئة (Finition)',
            'سنة الصنع',
            'اللون',
            'رقم الهيكل (VIN)',
            'سعر الشراء (تكلفة)',
            'رقم التتبع',
            'اسم العميل الكامل',
            'رقم جواز السفر',
            'رقم البطاقة الوطنية',
            'تكلفة الشحن',
            'تاريخ الوصول',
        ];

        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return new UploadedFile(
            $tempFile,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
