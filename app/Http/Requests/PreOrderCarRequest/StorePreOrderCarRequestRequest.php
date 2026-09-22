<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\PreOrderCar;
use App\Models\PreOrderCarRequest;
use Illuminate\Foundation\Http\FormRequest;

class StorePreOrderCarRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PreOrderCarRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Extra guards that need the route's PreOrderCar model, done here
     * (rather than in rules()) so we can give one clear Arabic message
     * per failure instead of a generic validation error.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var PreOrderCar|null $preOrderCar */
            $preOrderCar = $this->route('preOrderCar');

            if (! $preOrderCar || ! $preOrderCar->isPending()) {
                $validator->errors()->add('pre_order_car', 'هذه السيارة غير متاحة للطلب المسبق حاليًا');

                return;
            }

            if ($preOrderCar->requests()->where('customer_id', $this->input('customer_id'))->exists()) {
                $validator->errors()->add('customer_id', 'لقد قام هذا العميل بتقديم طلب على هذه السيارة مسبقًا');
            }
        });
    }
}
