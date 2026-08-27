<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Car extends Model
{
    use HasFactory;

    /**
     * The order's status is not maintained independently — it always
     * mirrors its car's status, in both directions (advancing or
     * reverting). Whenever a car's status changes, push that exact same
     * value onto its current (most recent) order.
     *
     * Only the current order is synced, not the full order history: an
     * older, already-completed order from a previous owner (before an
     * ownership transfer) must not be rewritten by a later car status
     * change that now belongs to a new order.
     */
    protected static function booted(): void
    {
        static::updated(function (Car $car) {
            if (! $car->wasChanged('status')) {
                return;
            }

            $order = $car->orders()->latest('id')->first();

            if ($order && $order->status !== $car->status) {
                $order->forceFill(['status' => $car->status])->saveQuietly();
            }
        });
    }

    /**
     * Lifecycle states for a car, mirroring the order lifecycle since a
     * car's own status tracks its journey from purchase to delivery.
     */
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_SHIPPING = 'shipping';

    public const STATUS_IN_SHOW_ROOM = 'in_show_room';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_SOLD = 'sold';
    
    protected $fillable = [
        'batch_id',
        'supplier_id',
        'container_opener_id',
        'brand',
        'model',
        'finition',
        'manufacture_year',
        'color',
        'vin',
        'foreign_purchase_price',
        'shipping_cost',
        'sale_price',
        'tracking_number',
        'container_no',
        'shipping_date',
        'arrival_date',
        'delivery_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_year' => 'integer',
            'foreign_purchase_price' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'shipping_date' => 'date',
            'arrival_date' => 'date',
            'delivery_date' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function containerOpener(): BelongsTo
    {
        return $this->belongsTo(ContainerOpener::class);
    }

    /**
     * Cost lines for this car (customs, transport, repairs, etc.).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(CarExpense::class);
    }

    /**
     * General company expenses tied directly to this car.
     */
    public function generalExpenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * All orders placed on this car.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The single active order this car is sold under, if any.
     * A car is expected to belong to at most one (non-cancelled) order.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * The first order ever placed on this car (first owner).
     */
    public function firstOrder(): HasOne
    {
        return $this->hasOne(Order::class)->oldestOfMany('id');
    }

    /**
     * The most recent / current active order on this car (current owner).
     * For a car sold only once, firstOrder === currentOrder.
     */
    public function currentOrder(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany('id');
    }

    /**
     * Documents attached to this car (invoices, customs papers, photos...).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Sum of this car's expense lines whose type indicates shipping
     * (Arabic "شحن" or English "shipping", case-insensitive). There is no
     * dedicated `expense_type` enum in the migration — it's a free-text
     * string — so this is a best-effort match on that text rather than an
     * exact category lookup. If your team standardizes on a specific
     * expense_type value for shipping (e.g. always exactly "شحن"), you can
     * tighten this to an exact `where('expense_type', 'شحن')` instead.
     */
    public function getShippingPriceAttribute(): float
    {
        return (float) $this->shipping_cost;
    }

    /**
     * Total of all car-specific expenses (local currency).
     */
    public function getTotalExpensesAttribute(): float
    {
        return (float) $this->expenses()->sum('local_amount')
            + (float) $this->generalExpenses()->sum('amount');
    }

    public function getTotalCostLocalAttribute(): float
    {
        $exchangeRate = (float) ($this->batch?->exchange_rate ?? Setting::where('key', 'current_exchange_rate')->value('value'));
        // (سعر الشراء + سعر الشحن) بالعملة الأجنبية × سعر الصرف
        // = تكلفة السيارة واصلة إلى الميناء، بالعملة المحلية
        return ((float) $this->foreign_purchase_price + (float) $this->shipping_cost) * $exchangeRate;
    }

    public function getProfitAttribute(): float
    {
        // سعر البيع في الميناء (محلي) - تكلفة الوصول للميناء (محلي) = الفائدة (محلي)
        return (float) $this->sale_price - $this->total_cost_local;
    }

    /**
     * Net profit estimate: sale price minus purchase price minus expenses.
     * This is a simple estimate for dashboards; real profit accounting
     * should go through a dedicated report/service.
     */
    public function getEstimatedProfitAttribute(): float
    {
        return $this->profit;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }
}