<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'agent_id'    => $this->agent_id,
            'agent_name'  => $this->agent?->name,
            //'agent'       => new AgentMiniResource($this->whenLoaded('agent')),
            'name'        => $this->name,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'national_id' => $this->national_id,
            'passport_no' => $this->passport_no,
            'address'     => $this->address,

            'orders_count' => $this->when(
                isset($this->orders_count) || $this->relationLoaded('orders'),
                fn() => $this->orders_count ?? $this->orders->count()
            ),

            // Financial summary:
            // • When loaded from AgentController::customers(), the values
            //   are pre-computed via withSum() on the query (zero extra
            //   queries) and arrive as total_paid_sum / total_remaining_sum.
            // • When explicitly requested with ?with_stats=1 by staff who
            //   hold the matching permissions, they are computed on the fly
            //   via the model accessors.
            // • Otherwise the fields are omitted entirely.
            'total_paid' => $this->when(
                isset($this->total_paid_sum)
                    || ($request->boolean('with_stats') && $request->user()?->can('customer_payments.view')),
                fn() => isset($this->total_paid_sum)
                    ? (float) $this->total_paid_sum
                    : (float) $this->total_paid
            ),
            'total_remaining' => $this->when(
                isset($this->total_remaining_sum)
                    || $this->relationLoaded('orders')
                    || ($request->boolean('with_stats') && $request->user()?->can('orders.view')),
                fn() => isset($this->total_remaining_sum)
                    ? (float) $this->total_remaining_sum
                    : ($this->relationLoaded('orders')
                        ? (float) $this->orders->sum('remaining_amount')
                        : (float) $this->total_remaining)
            ),

            // Uses OrderWithCarResource (not OrderMiniResource) so that
            // each order includes its car details (brand, model, price,
            // status...) — the data that's actually useful when viewing
            // a customer's profile. OrderMiniResource is kept for other
            // embedding contexts (e.g. inside CarResource) where
            // recursion must be avoided.
            'orders'   => OrderWithCarResource::collection($this->whenLoaded('orders')),
            'payments' => CustomerPaymentResource::collection($this->whenLoaded('payments')),

            // Profile documents (passport copy, national ID scan, etc.)
            'documents' => CustomerDocumentResource::collection($this->whenLoaded('customerDocuments')),

            // Linked user account summary (never expose password/tokens)
            'account' => $this->whenLoaded('user', fn() => $this->user ? [
                'id'         => $this->user->id,
                'email'      => $this->user->email,
                'is_active'  => $this->user->is_active,
                'last_login' => null, // extend later if you add last_login_at column
            ] : null),

            'created_by' => $this->created_by,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}