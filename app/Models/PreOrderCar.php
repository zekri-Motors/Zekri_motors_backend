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
        'brand',
        'model',
        'finition',
        'manufacture_year',
        'color',
        'price',
        'customs_fees',              // جمركة — سيارات جديدة (سنة الصنع ≥ السنة الحالية)
        'customs_fees_under_three',  // جمركة +3 — أقل من 3 سنوات (وليست جديدة)
        'preparation_days',          // مدة التجهيز (بالأيام)
        'shipping_days',             // مدة الشحن (بالأيام)
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price'                    => 'decimal:2',
            'customs_fees'             => 'decimal:2',
            'customs_fees_under_three' => 'decimal:2',
            'manufacture_year'         => 'integer',
            'preparation_days'         => 'string',
            'shipping_days'            => 'string',
            'published_at'             => 'datetime',
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

    private static function currentCalendarYear(): int
    {
        return (int) date('Y');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Customer pre-order requests for this catalog model.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PreOrderCarRequest::class);
    }

    /**
     * Images and videos attached to this pre-order catalog entry.
     */
    public function media(): HasMany
    {
        return $this->hasMany(CarMedia::class, 'pre_order_car_id');
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
     *
     * @throws \RuntimeException
     */
    public function approveRequest(PreOrderCarRequest $request, int $decidedBy): PreOrderCarRequest
    {
        // if (! $this->isPending()) {
        //     throw new \RuntimeException('سيارة الطلب المسبق ليست متاحة للموافقة على الطلبات');
        // }

        if ($request->pre_order_car_id !== $this->id) {
            throw new \RuntimeException('هذا الطلب لا يخص سيارة الطلب المسبق هذه');
        }

        if (! $request->isPending()) {
            throw new \RuntimeException('لا يمكن الموافقة إلا على طلب لا يزال قيد الانتظار (pending)');
        }

        $request->update([
            'status'      => PreOrderCarRequest::STATUS_COMPLETED,
            'decided_by'  => $decidedBy,
            'decided_at'  => now(),
        ]);

        return $request->fresh(['customer']);
    }
}