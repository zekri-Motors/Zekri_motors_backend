<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CarMedia
 */
class CarMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'car_id' => $this->car_id,
            'car' => $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'brand' => $this->car->brand,
                'model' => $this->car->model,
                'manufacture_year' => $this->car->manufacture_year,
                'vin' => $this->car->vin,
            ]),

            'type' => $this->type,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,

            'title' => $this->title,
            'is_cover' => $this->is_cover,
            'sort_order' => $this->sort_order,

            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),

            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
