<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PreOrderCar
 */
class PreOrderCarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierMiniResource($this->whenLoaded('supplier')),
            'container_opener_id' => $this->container_opener_id,

            'brand' => $this->brand,
            'model' => $this->model,
            'finition' => $this->finition,
            'manufacture_year' => $this->manufacture_year,
            'color' => $this->color,

            'price' => (float) $this->price,
            'customs_fees' => (float) $this->customs_fees,

            'status' => $this->status,

            'requests_count' => $this->when(
                $this->requests_count !== null,
                fn () => $this->requests_count
            ),
            'requests' => PreOrderCarRequestResource::collection($this->whenLoaded('requests')),

            'notes' => $this->notes,
            'created_by' => $this->created_by,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
