<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreOrderCar extends Model
{
    use HasFactory;

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
        'published_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'customs_fees' => 'decimal:2',
            'manufacture_year' => 'integer',
            'published_at' => 'datetime',
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
     * Contact-based pre-order requests for this catalog model.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PreOrderCarRequest::class);
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isPending(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Publish a catalog model so the public can submit pre-order requests.
     */
    public function publish(): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('لا يمكن نشر سيارة الطلب المسبق إلا قبل أن تُنشر لأول مرة');
        }

        $this->update(['published_at' => now()]);
    }

    /**
     * Mark a pending pre-order request as completed (staff approval).
     * Does not create orders, cars, batches, or link to customers.
     *
     * @throws \RuntimeException
     */
    public function approveRequest(PreOrderCarRequest $request, int $decidedBy): PreOrderCarRequest
    {
        if (! $this->isPending()) {
            throw new \RuntimeException('سيارة الطلب المسبق ليست متاحة للموافقة على الطلبات');
        }

        if ($request->pre_order_car_id !== $this->id) {
            throw new \RuntimeException('هذا الطلب لا يخص سيارة الطلب المسبق هذه');
        }

        if (! $request->isPending()) {
            throw new \RuntimeException('لا يمكن الموافقة إلا على طلب لا يزال قيد الانتظار (pending)');
        }

        $request->update([
            'status' => PreOrderCarRequest::STATUS_COMPLETED,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
        ]);

        return $request->fresh(['contact']);
    }
}
