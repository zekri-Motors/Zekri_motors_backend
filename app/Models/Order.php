<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $previousOrders = self::where('car_id', $order->car_id)
                ->where('customer_id', '!=', $order->customer_id)
                ->where('id', '!=', $order->id)
                ->get();

            foreach ($previousOrders as $oldOrder) {
                foreach ($oldOrder->payments as $payment) {
                    $creatorId = Auth::id() ?? $payment->created_by ?? (\App\Models\User::first()?->id ?? 1);

                    if ($payment->wasCollectedByAgent()) {
                        $previousBalance = (float) (AgentTransaction::where('agent_id', $payment->agent_id)
                            ->latest('id')
                            ->value('current_balence') ?? 0);
                        $newBalance = $previousBalance + (float) $payment->amount;

                        AgentTransaction::create([
                            'agent_id' => $payment->agent_id,
                            'direction' => AgentTransaction::DIRECTION_IN,
                            'amount' => $payment->amount,
                            'previous_balence' => $previousBalance,
                            'current_balence' => $newBalance,
                            'payment_id' => $payment->id,
                            'transaction_date' => now()->toDateString(),
                            'notes' => 'إلغاء قبض دفعة عميل محذوفة رقم #' . $payment->id . ' بسبب انتقال ملكية السيارة',
                            'created_by' => $creatorId,
                        ]);
                    } else {
                        $previousBalance = (float) (TreasuryTransaction::approved()->latest('id')->value('current_balence') ?? 0);
                        $newBalance = $previousBalance - (float) $payment->amount;

                        TreasuryTransaction::create([
                            'direction' => TreasuryTransaction::DIRECTION_OUT,
                            'amount' => $payment->amount,
                            'previous_balence' => $previousBalance,
                            'current_balence' => $newBalance,
                            'source_type' => TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT,
                            'source_id' => $payment->id,
                            'transaction_date' => now()->toDateString(),
                            'status' => TreasuryTransaction::STATUS_APPROVED,
                            'notes' => 'إلغاء دفعة عميل محذوفة رقم #' . $payment->id . ' بسبب انتقال ملكية السيارة',
                            'created_by' => $creatorId,
                        ]);
                    }
                    $payment->delete();
                }

                $oldOrder->invoice()?->delete();
                $oldOrder->forceFill([
                    'paid_amount' => 0,
                    'remaining_amount' => 0,
                ])->save();
            }
        });
    }

    /**
     * Order lifecycle. Deliberately identical, name-for-name, to Car's own
     * STATUS_* constants: an order's status is not tracked independently
     * — it is always a mirror of its car's status (see Car::booted()),
     * updated automatically in both directions whenever the car's status
     * changes. There is no separate order-status vocabulary anymore.
     */
    public const STATUS_AVAILABLE = Car::STATUS_AVAILABLE;

    public const STATUS_SHIPPING = Car::STATUS_SHIPPING;

    public const STATUS_IN_SHOW_ROOM = Car::STATUS_IN_SHOW_ROOM;

    public const STATUS_DELIVERED = Car::STATUS_DELIVERED;

    public const STATUS_SOLD = Car::STATUS_SOLD;

    /**
     * Ordered list of valid statuses, used to validate forward transitions.
     */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_SHIPPING,
        self::STATUS_IN_SHOW_ROOM,
        self::STATUS_DELIVERED,
        self::STATUS_SOLD,
    ];

    protected $fillable = [
        'order_number',
        'customer_id',
        'car_id',
        'agent_id',
        'status',
        'purchase_date',
        'shipping_date',
        'arrival_date',
        'delivery_date',
        'paid_amount',
        'remaining_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'shipping_date' => 'date',
            'arrival_date' => 'date',
            'delivery_date' => 'date',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Payments the customer has made towards this specific order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Expenses tied to fulfilling this order (delivery, paperwork, etc.).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The single invoice issued for this order, if any.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Recalculate paid/remaining amounts from the actual payments table,
     * and persist them. Call this after creating/voiding a payment so the
     * order's cached totals never drift from the ledger.
     */
    public function recalculateBalance(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $price = (float) $this->car?->sale_price + (float) ($this->car?->total_expenses ?? 0);

        $this->forceFill([
            'paid_amount' => $paid,
            'remaining_amount' => max($price - $paid, 0),
        ])->save();
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
