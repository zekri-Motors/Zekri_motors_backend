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
        $pivot = $this->pivot;
        $carId = $pivot?->car_id;
        $preOrderCarId = $pivot?->pre_order_car_id;
        $cars = $this->relationLoaded('cars') ? $this->cars : collect();
        $preOrderCars = $this->relationLoaded('preOrderCars') ? $this->preOrderCars : collect();

        return [
            'id' => $this->id,
            'vehicle_type' => $carId !== null ? 'car' : ($preOrderCarId !== null ? 'pre_order_car' : null),
            'vehicle_id' => $carId ?? $preOrderCarId,
            'car_id' => $carId,
            'pre_order_car_id' => $preOrderCarId,
            'vehicles' => $this->when($this->relationLoaded('cars') || $this->relationLoaded('preOrderCars'), function () use ($cars, $preOrderCars) {
                return collect($cars)->map(fn ($car) => ['type' => 'car', 'id' => $car->id, 'brand' => $car->brand, 'model' => $car->model, 'manufacture_year' => $car->manufacture_year, 'vin' => $car->vin])
                    ->merge(collect($preOrderCars)->map(fn ($car) => ['type' => 'pre_order_car', 'id' => $car->id, 'brand' => $car->brand, 'model' => $car->model, 'manufacture_year' => $car->manufacture_year]))
                    ->values();
            }),

            'type' => $this->type,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,

            'title' => $this->title,
            'is_cover' => (bool) ($pivot?->is_cover ?? false),
            'sort_order' => (int) ($pivot?->sort_order ?? 0),

            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),

            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
