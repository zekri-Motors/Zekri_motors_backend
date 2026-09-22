<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class StorePreOrderCarRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // NOTE: adjust to match how customers actually authenticate in
        // this project. If customers have their own guard/portal, prefer
        // something like: return $this->user('customer') !== null;
        // and drop customer_id from the rules below in favor of
        // $this->user('customer')->id inside the controller. Left as an
        // explicit input field here since that auth setup isn't in
        // context yet — this assumes staff/admin submit the request on
        // the customer's behalf, or a single shared guard is used.
        return $this->user() !== null;
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
            $preOrderCar = $this->route('pre_order_car');

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
