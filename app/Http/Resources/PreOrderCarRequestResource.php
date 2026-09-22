<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PreOrderCarRequest
 */
class PreOrderCarRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pre_order_car_id' => $this->pre_order_car_id,
            'customer_id' => $this->customer_id,
            // NOTE: assumes a CustomerMiniResource exists, mirroring
            // SupplierMiniResource used in BatchResource. Swap for your
            // actual Customer resource if the name differs.
            'customer' => new CustomerMiniResource($this->whenLoaded('customer')),

            'status' => $this->status,
            'notes' => $this->notes,

            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
