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
            'id'                        => $this->id,
            'brand'                     => $this->brand,
            'model'                     => $this->model,
            'finition'                  => $this->finition,
            'manufacture_year'          => $this->manufacture_year,
            'color'                     => $this->color,

            'price'                     => (float) $this->price,
            'customs_fees'              => (float) $this->customs_fees,              // جمركة (جديدة)
            'customs_fees_under_three'  => (float) $this->customs_fees_under_three,  // جمركة +3
            'preparation_days'          => $this->preparation_days,          // مدة التجهيز
            'shipping_days'             => $this->shipping_days,             // مدة الشحن

            'published_at'              => $this->published_at,
            'is_published'              => $this->published_at !== null,

            'requests_count'            => $this->when(
                $this->requests_count !== null,
                fn () => $this->requests_count
            ),
            'requests'                  => PreOrderCarRequestResource::collection($this->whenLoaded('requests')),

            'media'                     => CarMediaResource::collection($this->whenLoaded('media')),

            'created_by'                => $this->created_by,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}