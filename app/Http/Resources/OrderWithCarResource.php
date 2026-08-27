<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order representation used when nested inside CustomerResource.
 * Unlike OrderMiniResource (which is a bare reference with 4 fields,
 * designed to be embedded inside CarResource without recursion),
 * this variant includes the car details that a customer needs to see
 * at a glance on their profile: what car they ordered, its status,
 * and how much they've paid vs. what's remaining.
 *
 * @mixin \App\Models\Order
 */
class OrderWithCarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'order_number'   => $this->order_number,
            'status'         => $this->status,
            'status_label'   => $this->statusLabel(),

            // Car details — already eager-loaded via orders.car (plus
            // orders.car.firstOrder.customer / orders.car.currentOrder.customer)
            // in CustomerController::show(), so no extra queries here.
            // Delegated to CarResource itself (rather than a hand-picked
            // array) so this stays in sync with CarResource's field list,
            // its cost/operational-data permission gating, and its
            // first_owner/current_owner ownership-history fields.
            'car' => new CarResource($this->whenLoaded('car')),

            'purchase_date'  => $this->purchase_date?->format('Y-m-d'),
            'shipping_date'  => $this->shipping_date?->format('Y-m-d'),
            'arrival_date'   => $this->arrival_date?->format('Y-m-d'),
            'delivery_date'  => $this->delivery_date?->format('Y-m-d'),

            'paid_amount'      => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,

            'notes'      => $this->notes,
            'created_at' => $this->created_at,
        ];
    }

    protected function statusLabel(): string
    {
        return match ($this->status) {
            \App\Models\Order::STATUS_AVAILABLE => 'متوفر',
            \App\Models\Order::STATUS_SHIPPING => 'قيد الشحن',
            \App\Models\Order::STATUS_IN_SHOW_ROOM => 'وصل إلى صالة العرض',
            \App\Models\Order::STATUS_SOLD => 'مباع',
            \App\Models\Order::STATUS_DELIVERED => 'تم التسليم',
            default                                     => $this->status,
        };
    }
}