<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'amount',
        'received_by',
        'agent_id',
        'remittance_id',
        'attachment',
        'payment_date',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            if (Auth::check()) {
                $payment->received_by = $payment->received_by ?: Auth::user()->id;
                $payment->created_by = $payment->created_by ?: Auth::user()->id;
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The agent who physically collected this payment, when received_by
     * is "agent". Null when the company collected it directly.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The agent-transaction record representing the agent remitting this
     * collected payment back to the company (closes the loop between the
     * agent collecting cash and the company actually receiving it).
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(AgentTransaction::class, 'remittance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function wasCollectedByAgent(): bool
    {
        return $this->agent_id !== null;
    }

    /**
     * The treasury transaction representing the transfer to the general treasury.
     */
    public function generalTreasuryTransfer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TreasuryTransaction::class, 'source_id')
            ->where('source_type', TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('direction', TreasuryTransaction::DIRECTION_OUT);
    }

    /**
     * Determine if this customer payment has reached/been transferred to the company treasury.
     */
    public function getIsTransferredToTreasuryAttribute(): bool
    {
        return $this->agent_id === null || $this->remittance_id !== null;
    }



    /**
     * Get the general treasury transfer status (pending, approved, or null).
     */
    public function getGeneralTreasuryTransferStatusAttribute(): ?string
    {
        if ($this->agent_id === null && $this->receiver && $this->receiver->hasAnyRole(['admin', 'super-admin'])) {
            return 'approved';
        }
        return $this->generalTreasuryTransfer?->status;
    }
}
