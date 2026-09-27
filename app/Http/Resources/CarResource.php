<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Car
 */
class CarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Two distinct visibility tiers, per the explicit requirement:
        // "Admin: صلاحية كاملة على جميع أجزاء النظام ما عدا أسعار الشراء
        // والشحن والفائدة لكل سيارة" — i.e. Admin manages cars fully
        // (including which supplier/container-opener a car came from —
        // that's operational data, not a cost figure) but never sees the
        // purchase cost, shipping cost breakdown, or profit. Only
        // Super Admin holds cars.view_cost.
        $canSeeCosts = $request->user()?->can('cars.view_cost') ?? false;

        // Operational data (supplier identity, batch, container opener)
        // is visible to anyone with general cars.view — a customer/agent
        // browsing the catalogue still only sees brand/model/sale price
        // etc., gated separately below by cars.view vs the cost tier.
        $canSeeOperationalData = $request->user()?->can('suppliers.view') ?? false;

        return [
            'id' => $this->id,
            'batch_id' => $this->when($canSeeOperationalData, $this->batch_id),
            'batch' => $this->when($canSeeOperationalData && $this->relationLoaded('batch'), fn() => [
                'id'            => $this->batch->id,
                'exchange_rate' => $this->batch->exchange_rate !== null
                    ? (float) $this->batch->exchange_rate
                    : null,
                'status'        => $this->batch->status,
            ]),
            'supplier_id' => $this->when($canSeeOperationalData, $this->supplier_id),
            'container_opener_id' => $this->when($canSeeOperationalData, $this->container_opener_id),
            'supplier' => $this->when($canSeeOperationalData, fn() => new SupplierMiniResource($this->whenLoaded('supplier'))),
            'container_opener' => $this->when($canSeeOperationalData, fn() => new ContainerOpenerResource($this->whenLoaded('containerOpener'))),

            'brand' => $this->brand,
            'model' => $this->model,
            'finition' => $this->finition,
            'manufacture_year' => $this->manufacture_year,
            'color' => $this->color,
            'vin' => $this->vin,

            // Cost tier: purchase price only, NOT gated by general
            // operational visibility — an admin can see the supplier this
            // car came from (operational) but never this figure (cost).
            'foreign_purchase_price' => $this->when($canSeeCosts, (float) $this->foreign_purchase_price),
            'shipping_cost' => $this->when($canSeeCosts, (float) $this->shipping_cost),
            'exchange_rate' => $this->when($canSeeCosts, (float) ($this->batch?->exchange_rate ?? Setting::where('key','current_exchange_rate')->value('value'))),
            'total_cost_local' => $this->when($canSeeCosts, (float) $this->total_cost_local),
            'profit' => $this->when($canSeeCosts, (float) $this->profit),
            'sale_price' => (float) $this->sale_price,

            'tracking_number' => $this->tracking_number,
            'container_no' => $this->container_no,
            'shipping_date' => $this->shipping_date?->format('Y-m-d'),
            'arrival_date' => $this->arrival_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),

            'status' => $this->status,
            'notes' => $this->notes,

            // Financial summary (expense breakdown, profit) - cost tier
            // only, only computed when the relations are loaded.
            'total_expenses' => $this->when(
                $canSeeCosts && $this->relationLoaded('expenses') && $this->relationLoaded('generalExpenses'),
                fn() => $this->total_expenses
            ),
            'estimated_profit' => $this->when(
                $canSeeCosts,
                fn() => $this->estimated_profit
            ),

            'is_sold' => $this->isSold(),

            // ── Ownership history ─────────────────────────────────────────
            // Visible to anyone with operational data access (admin+).
            // Shows the first customer who ever bought this car and the
            // current/most-recent owner — they differ only when a car was
            // returned and re-sold, which is rare but possible.
            'first_owner' => $this->when(
                $this->relationLoaded('firstOrder'),
                fn() => $this->firstOrder && $this->firstOrder->customer
                    ? new CustomerResource($this->firstOrder->customer)
                    : null
            ),
            'current_owner' => $this->when(
                $this->relationLoaded('currentOrder'),
                fn() => $this->currentOrder && $this->currentOrder->customer
                    ? new CustomerResource($this->currentOrder->customer)
                    : null
            ),

            // The itemized expense list (shipping, customs, repairs...)
            // is cost-tier data even though "who can edit the car" is
            // broader — an admin can ADD an expense (cars.update) without
            // being able to SEE the resulting cost breakdown here.
            'expenses' => $this->when($canSeeCosts, fn() => CarExpenseResource::collection($this->whenLoaded('expenses'))),
            'media' => CarMediaResource::collection($this->whenLoaded('media')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'order' => new \App\Http\Resources\OrderMiniResource($this->whenLoaded('order')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
