<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for POST /pre-order-car-requests (flat route).
 * Unlike the nested version, this request includes pre_order_car_id
 * as a required body field instead of a route parameter.
 */
class StorePreOrderCarRequestDirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pre_order_car_requests.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pre_order_car_id' => ['required', 'integer', 'exists:pre_order_cars,id'],
            'customer_id'      => ['required', 'integer', 'exists:customers,id'],
            'notes'            => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pre_order_car_id.required' => 'معرّف السيارة إلزامي',
            'pre_order_car_id.exists'   => 'السيارة المحددة غير موجودة في النظام',
            'customer_id.required'      => 'معرّف العميل إلزامي',
            'customer_id.exists'        => 'العميل المحدد غير موجود في النظام',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $preOrderCarId = $this->input('pre_order_car_id');
            $customerId    = $this->input('customer_id');

            if (! $preOrderCarId) {
                return;
            }

            /** @var PreOrderCar|null $preOrderCar */
            $preOrderCar = PreOrderCar::find($preOrderCarId);

            if (! $preOrderCar || ! $preOrderCar->isPending()) {
                $validator->errors()->add('pre_order_car_id', 'هذه السيارة غير متاحة للطلب المسبق حاليًا');
                return;
            }

            if ($customerId && $preOrderCar->requests()->where('customer_id', $customerId)->exists()) {
                $validator->errors()->add('customer_id', 'قدّم هذا العميل طلباً مسبقاً على هذه السيارة مسبقًا');
            }
        });
    }
}
