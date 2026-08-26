<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\SupplierPayment
 */
class SupplierPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'status' => $this->batch->status,
            ]),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierMiniResource($this->whenLoaded('supplier')),

            'amount_foreign' => (float) $this->amount_foreign,
            'exchange_rate' => (float) $this->exchange_rate,
            'amount_local' => (float) $this->amount_local,

            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,

            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'notes' => $this->notes,

            'created_by' => $this->creator->name,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
