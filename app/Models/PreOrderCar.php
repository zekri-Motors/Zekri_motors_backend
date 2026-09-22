<?php

namespace App\Models;

use App\Services\OrderNumberGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PreOrderCar extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'supplier_id',
        'container_opener_id',
        'brand',
        'model',
        'finition',
        'manufacture_year',
        'color',
        'price',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'manufacture_year' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function containerOpener(): BelongsTo
    {
        return $this->belongsTo(ContainerOpener::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Every customer request submitted for this pre-order car — several
     * different customers may each have their own row here.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PreOrderCarRequest::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Move a freshly imported pre-order car from draft into pending,
     * making it visible to customers and open for requests.
     */
    public function publish(): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('لا يمكن نشر سيارة الطلب المسبق إلا وهي في حالة مسودة (draft)');
        }

        $this->update(['status' => self::STATUS_PENDING]);
    }

    /**
     * Approve one customer's request for this pre-order car:
     *
     *   1. every OTHER pending request on this same car is auto-rejected
     *   2. the chosen request is marked "approved"
     *   3. a real Batch (one supplier, one car) + Car + Order are created
     *      for the winning customer, exactly like a normal batch import
     *   4. this pre-order car itself is marked "completed"
     *
     * The whole operation is atomic — either everything above happens, or
     * nothing does.
     *
     * @throws \RuntimeException if the car isn't open for a decision, or
     *                            the request doesn't belong to this car /
     *                            isn't itself pending.
     */
    public function approveRequest(PreOrderCarRequest $request, int $decidedBy): Order
    {
        if (! $this->isPending()) {
            throw new \RuntimeException('سيارة الطلب المسبق ليست بحالة تسمح بالموافقة على طلب (يجب أن تكون pending)');
        }

        if ($request->pre_order_car_id !== $this->id) {
            throw new \RuntimeException('هذا الطلب لا يخص سيارة الطلب المسبق هذه');
        }

        if (! $request->isPending()) {
            throw new \RuntimeException('لا يمكن الموافقة إلا على طلب لا يزال قيد الانتظار (pending)');
        }

        return DB::transaction(function () use ($request, $decidedBy) {
            // 1. Auto-reject every other still-pending request on this car.
            $this->requests()
                ->where('id', '!=', $request->id)
                ->where('status', PreOrderCarRequest::STATUS_PENDING)
                ->update([
                    'status' => PreOrderCarRequest::STATUS_REJECTED,
                    'decided_by' => $decidedBy,
                    'decided_at' => now(),
                ]);

            // 2. Approve the winning request.
            $request->update([
                'status' => PreOrderCarRequest::STATUS_APPROVED,
                'decided_by' => $decidedBy,
                'decided_at' => now(),
            ]);

            // 3. A "batch of one" that represents this specific car's real
            // shipment — keeps this Car consistent with every other Car
            // row in the system, since Car::batch_id is required there too.
            $batch = Batch::create([
                'supplier_id' => $this->supplier_id,
                'purchase_date' => now()->toDateString(),
                'notes' => "تم إنشاؤه تلقائيًا من الطلب المسبق رقم #{$this->id}",
                'status' => Batch::STATUS_PARTIAL,
            ]);

            $car = Car::create([
                'batch_id' => $batch->id,
                'supplier_id' => $this->supplier_id,
                'container_opener_id' => $this->container_opener_id,
                'brand' => $this->brand,
                'model' => $this->model,
                'finition' => $this->finition,
                'manufacture_year' => $this->manufacture_year,
                'color' => $this->color,
                'vin' => null,
                // Only one price was ever collected for a pre-order car;
                // it is used as both sides here, same fallback the normal
                // import uses when a sale price isn't given separately.
                'foreign_purchase_price' => (float) $this->price,
                'shipping_cost' => 0,
                'sale_price' => (float) $this->price,
                'tracking_number' => null,
                'arrival_date' => null,
                'status' => Car::STATUS_SHIPPING,
            ]);

            $batch->update(['cars_count' => 1]);
            $batch->recomputeTotalCostForeign(save: false);
            $batch->recomputeExchangeRate(save: true);

            $order = Order::create([
                'order_number' => OrderNumberGenerator::generate(),
                'customer_id' => $request->customer_id,
                'car_id' => $car->id,
                'status' => Order::STATUS_SHIPPING,
                'purchase_date' => $batch->purchase_date,
                'shipping_date' => now()->toDateString(),
                'arrival_date' => null,
                'paid_amount' => 0,
                'remaining_amount' => $car->sale_price,
                'created_by' => $decidedBy,
            ]);

            // 4. Close the pre-order car out.
            $this->update(['status' => self::STATUS_COMPLETED]);

            return $order;
        });
    }
}
