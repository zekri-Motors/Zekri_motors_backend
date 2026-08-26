<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\AgentTransaction
 */
class AgentTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'direction' => $this->direction,
            'amount' => (float) $this->amount,
            'previous_balence' => (float) $this->previous_balence,
            'current_balence' => (float) $this->current_balence,

            'payment_id' => $this->payment_id,
            'customer_payment' => $this->whenLoaded('customerPayment', fn () => $this->customerPayment ? [
                'id' => $this->customerPayment->id,
                'order_id' => $this->customerPayment->order_id,
                'amount' => (float) $this->customerPayment->amount,
            ] : null),

            'transaction_id' => $this->transaction_id,
            'treasury_transaction' => $this->whenLoaded('treasuryTransaction', fn () => $this->treasuryTransaction ? [
                'id' => $this->treasuryTransaction->id,
                'direction' => $this->treasuryTransaction->direction,
                'amount' => (float) $this->treasuryTransaction->amount,
                'status' => $this->treasuryTransaction->status,
                'approved_by' => $this->treasuryTransaction->approver?->name,
                'approved_at' => $this->treasuryTransaction->approved_at?->format('Y-m-d H:i:s'),
                'approver' => $this->treasuryTransaction->relationLoaded('approver') && $this->treasuryTransaction->approver ? [
                    'id' => $this->treasuryTransaction->approver->id,
                    'name' => $this->treasuryTransaction->approver->name,
                ] : null,
            ] : null),

            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,
            'notes' => $this->notes,

            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
