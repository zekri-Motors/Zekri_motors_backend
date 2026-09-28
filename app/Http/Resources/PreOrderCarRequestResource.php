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
            'id'              => $this->id,

            // السيارة المرتبطة
            'pre_order_car_id' => $this->pre_order_car_id,
            'pre_order_car'    => new PreOrderCarResource($this->whenLoaded('preOrderCar')),

            // العميل
            'customer_id' => $this->customer_id,
            'customer'    => new CustomerResource($this->whenLoaded('customer')),

            // الحالة والملاحظات
            'status' => $this->status,
            'notes'  => $this->notes,

            // معلومات الموافقة
            'decided_by'      => $this->decided_by,
            'decided_by_user' => $this->whenLoaded('decidedByUser', fn () => [
                'id'   => $this->decidedByUser->id,
                'name' => $this->decidedByUser->name,
            ]),
            'decided_at' => $this->decided_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
