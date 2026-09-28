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
            'vehicle_type' => $this->car_id !== null ? 'car' : 'pre_order_car',
            'vehicle_id' => $this->car_id ?? $this->pre_order_car_id,
            'vehicle' => $this->when(
                $this->relationLoaded('car') || $this->relationLoaded('preOrderCar'),
                function () {
                    $vehicle = $this->car_id !== null ? $this->car : $this->preOrderCar;

                    return $vehicle ? [
                        'id' => $vehicle->id,
                        'brand' => $vehicle->brand,
                        'model' => $vehicle->model,
                        'manufacture_year' => $vehicle->manufacture_year,
                        'vin' => $vehicle instanceof \App\Models\Car ? $vehicle->vin : null,
                    ] : null;
                }
            ),
            'car_id' => $this->car_id,
            'car' => $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'brand' => $this->car->brand,
                'model' => $this->car->model,
                'manufacture_year' => $this->car->manufacture_year,
                'vin' => $this->car->vin,
            ]),
            'pre_order_car_id' => $this->pre_order_car_id,
            'pre_order_car' => $this->whenLoaded('preOrderCar', fn () => [
                'id' => $this->preOrderCar->id,
                'brand' => $this->preOrderCar->brand,
                'model' => $this->preOrderCar->model,
                'manufacture_year' => $this->preOrderCar->manufacture_year,
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
