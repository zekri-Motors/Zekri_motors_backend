<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\Customer;
use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class StorePreOrderCarRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes'       => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'معرّف العميل إلزامي',
            'customer_id.exists'   => 'العميل المحدد غير موجود في النظام',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var PreOrderCar|null $preOrderCar */
            $preOrderCar = $this->route('preOrderCar');

            if (! $preOrderCar || ! $preOrderCar->isPending()) {
                $validator->errors()->add('pre_order_car', 'هذه السيارة غير متاحة للطلب المسبق حاليًا');

                return;
            }

            $customerId = $this->input('customer_id');

            if ($customerId && $preOrderCar->requests()->where('customer_id', $customerId)->exists()) {
                $validator->errors()->add('customer_id', 'قدّم هذا العميل طلباً مسبقاً على هذه السيارة مسبقًا');
            }
        });
    }
}
