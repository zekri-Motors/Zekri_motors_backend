<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    /**
     * Lifecycle states for an import batch.
     */

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FULLY_PAID = 'fully_paid';

    protected $fillable = [
        'batch_number',
        'supplier_id',
        'purchase_date',
        'total_cost_foreign',
        'exchange_rate',
        'total_paid_amount_foreign', // auto-computed: sum of all supplier_payments.amount_foreign
        'status',
        'cars_count',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (Batch $batch): void {
            if ($batch->batch_number !== null) {
                return;
            }

            $batch->forceFill([
                'batch_number' => static::makeBatchNumber($batch->id),
            ])->saveQuietly();
        });
    }

    public static function makeBatchNumber(int $id): string
    {
        return 'BATCH-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total_cost_foreign' => 'decimal:2',
            'total_paid_amount_foreign' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Cars that were imported as part of this batch.
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Payments made towards this batch's supplier.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Total amount paid (local currency) for this batch so far.
     */
    public function getTotalPaidLocalAttribute(): float
    {
        return (float) $this->payments()->sum('amount_local');
    }

    /**
     * Recompute and persist total_cost_foreign as the sum of
     * foreign_purchase_price across every car belonging to this batch.
     *
     * total_cost_foreign is no longer entered by the user: it is fully
     * derived from the batch's cars, since it also serves as the
     * denominator of the recomputeExchangeRate() formula.
     *
     * Callers that add/remove/edit a car's price should call this
     * afterwards, then follow it with recomputeExchangeRate() to keep the
     * stored rate consistent with the new total (the rate's denominator
     * just changed even if no supplier_payment was touched).
     *
     * @param  bool  $save  Persist immediately (default true). Pass false
     *                      when the caller is about to call
     *                      recomputeExchangeRate(save: true) right after,
     *                      which will save both fields in one query.
     */
    public function recomputeTotalCostForeign(bool $save = true): void
    {
        $this->total_cost_foreign = $this->cars()->sum('foreign_purchase_price');

        if ($save) {
            $this->save();
        }
    }

    /**
     * Recompute and persist the batch's effective exchange_rate from the
     * live set of supplier_payments, applying the following formula:
     *
     *   ─────────────────────────────────────────────────────────────────
     *   STEP 1 — Paid portion (weighted average by amount_foreign):
     *
     *     paid_weighted_rate =
     *       Σ(pmt.amount_foreign × pmt.exchange_rate) / Σ(pmt.amount_foreign)
     *
     *   STEP 2 — Remaining unpaid portion:
     *
     *     remaining_foreign = total_cost_foreign − Σ(pmt.amount_foreign)
     *
     *     If remaining_foreign > 0 we conservatively assume the outstanding
     *     amount will be settled at the HIGHEST rate already seen for this
     *     batch (worst-case / most expensive assumption for what has not
     *     yet been paid).
     *
     *   STEP 3 — Blend both portions into one effective batch rate:
     *
     *     exchange_rate =
     *       ( Σ(pmt.amount_foreign × pmt.exchange_rate)
     *         + remaining_foreign × max_rate )
     *       / total_cost_foreign
     *   ─────────────────────────────────────────────────────────────────
     *
     * Edge-cases handled:
     *
     *   • No payments yet, total_cost_foreign known
     *       → exchange_rate = NULL (no rate data at all; do not store 0
     *         which would silently produce wrong financial figures).
     *
     *   • No payments yet, total_cost_foreign NULL
     *       → exchange_rate = NULL.
     *
     *   • All paid (remaining_foreign ≤ 0)
     *       → exchange_rate = paid_weighted_rate (pure weighted average;
     *         the max_rate fallback is not needed).
     *
     *   • Overpaid (payments exceed total_cost_foreign)
     *       → remaining_foreign treated as 0; rate = paid_weighted_rate.
     *         The overpayment itself is a data/business issue that should
     *         be flagged separately, not silently distorted in the rate.
     *
     * This method is called by SupplierPaymentController every time a
     * payment for this batch is created, updated, or deleted. It reads
     * the payments from the database (not from any in-memory collection)
     * so it is always consistent with the persisted state, even after
     * soft-deletes.
     *
     * @param  bool  $save  Persist immediately (default true). Pass false
     *                      when the caller is about to call $batch->save()
     *                      anyway to avoid a redundant extra query.
     */
    public function recomputeExchangeRate(bool $save = true): void
    {
        // Load all (non-soft-deleted) payments for this batch.
        // We need two aggregates: SUM(amount_foreign * exchange_rate)
        // and SUM(amount_foreign). Rather than loading all rows into PHP
        // we let the DB compute both in a single query.
        $aggregate = $this->payments()
            ->selectRaw('
                SUM(amount_foreign)                          AS total_foreign,
                SUM(amount_foreign * exchange_rate)          AS weighted_sum,
                MAX(exchange_rate)                           AS max_rate,
                COUNT(*)                                     AS payment_count
            ')
            ->first();

        $paymentCount = (int) ($aggregate->payment_count ?? 0);
        $totalForeign = (float) ($aggregate->total_foreign ?? 0);
        $weightedSum = (float) ($aggregate->weighted_sum ?? 0);
        $maxRate = (float) ($aggregate->max_rate ?? 0);
        $totalCost = (float) ($this->total_cost_foreign ?? 0);

        if ($paymentCount === 0 || $totalCost <= 0) {
            if ($paymentCount === 0) {
                // لا توجد دفعات، نجلب سعر الصرف الحالي من الإعدادات
                $this->exchange_rate = Setting::where('key', 'current_exchange_rate')
                    ->value('value');
            } else {
                $this->exchange_rate = $this->payments()->latest('id')->value('exchange_rate');
            }
        } else {
            $remainingForeign = max($totalCost - $totalForeign, 0.0);

            // Weighted sum for the already-paid portion is $weightedSum.
            // Add the unpaid portion priced at the worst (highest) rate.
            $blendedWeightedSum = $weightedSum + ($remainingForeign * $maxRate);

            // Divide by the total cost to get the blended effective rate.
            $this->exchange_rate = round($blendedWeightedSum / $totalCost, 4);
        }

        // Always keep total_paid_amount_foreign in sync at the same time,
        // since we already fetched the aggregate — no extra query needed.
        $this->total_paid_amount_foreign = $totalForeign;

        if ($totalCost > 0 && $this->total_paid_amount_foreign >= $totalCost) {
            $this->status = self::STATUS_FULLY_PAID;
        } else {
            $this->status = self::STATUS_PARTIAL;
        }

        if ($save) {
            $this->save();
        }
    }
}
