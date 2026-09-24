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
        'customs_fees',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'customs_fees' => 'decimal:2',
            'manufacture_year' => 'integer',
        ];
    }

    /**
     * Pre-order catalog entries are limited to brand-new models or cars
     * whose manufacture year is less than three calendar years old.
     */
    public static function isEligibleManufactureYear(int $manufactureYear): bool
    {
        return (self::currentCalendarYear() - $manufactureYear) < 3;
    }

    public static function isNewManufactureYear(int $manufactureYear): bool
    {
        return $manufactureYear >= self::currentCalendarYear();
    }

    /**
     * Pick the customs cell that matches the row's manufacture year.
     *
     * @throws \RuntimeException
     */
    public static function resolveCustomsFees(int $manufactureYear, mixed $customsNew, mixed $customsUnderThree): float
    {
        if (! self::isEligibleManufactureYear($manufactureYear)) {
            throw new \RuntimeException('الطلب المسبق متاح فقط للسيارات الجديدة أو التي عمرها أقل من 3 سنوات');
        }

        if (self::isNewManufactureYear($manufactureYear)) {
            if (! is_numeric($customsNew) || (float) $customsNew < 0) {
                throw new \RuntimeException('مصاريف الجمركة (جديدة) غير صالحة لهذه السنة');
            }

            return (float) $customsNew;
        }

        if (! is_numeric($customsUnderThree) || (float) $customsUnderThree < 0) {
            throw new \RuntimeException('مصاريف الجمركة (أقل من 3 سنوات) غير صالحة لهذه السنة');
        }

        return (float) $customsUnderThree;
    }

    private static function currentCalendarYear(): int
    {
        return (int) date('Y');
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
     * Approve one customer's request for this catalog model:
     *
     *   1. the approved request is marked "approved"
     *   2. a real Batch (one car) + Car + Order are created for that customer
     *   3. other pending requests on the same model stay untouched — the
     *      pre-order car remains "pending" so every request can be approved
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
            $request->update([
                'status' => PreOrderCarRequest::STATUS_APPROVED,
                'decided_by' => $decidedBy,
                'decided_at' => now(),
            ]);

            $batch = Batch::create([
                'supplier_id' => $this->supplier_id,
                'purchase_date' => now()->toDateString(),
                'notes' => "تم إنشاؤه تلقائيًا من الطلب المسبق (نموذج) رقم #{$this->id} — طلب عميل #{$request->id}",
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
                'foreign_purchase_price' => (float) $this->price,
                'shipping_cost' => 0,
                'sale_price' => (float) $this->price,
                'tracking_number' => null,
                'arrival_date' => null,
                'status' => Car::STATUS_SHIPPING,
            ]);

            if ((float) $this->customs_fees > 0) {
                CarExpense::create([
                    'car_id' => $car->id,
                    'expense_type' => 'جمركة',
                    'foreign_amount' => 0,
                    'local_amount' => (float) $this->customs_fees,
                    'notes' => 'من نموذج الطلب المسبق',
                ]);
            }

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

            return $order;
        });
    }
}
