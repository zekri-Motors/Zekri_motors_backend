<?php

namespace App\Http\Requests\CustomerPayment;

use App\Models\CustomerPayment;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerPayment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            // Required only when an agent physically collected the cash.
            'agent_id' => [
                'nullable', 'integer', 'exists:agents,id'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_id.required_if' => 'يجب تحديد الوكيل عند استلام الدفعة بواسطة وكيل',
        ];
    }

    /**
     * Cross-field checks:
     *   1. The order must actually belong to the given customer.
     *   2. The payment must not exceed the order's remaining balance —
     *      prevents overpaying an order by mistake.
     *   3. If the requester is an agent (not full customer_payments
     *      visibility), they may only record payments they collected
     *      themselves (agent_id forced to their own id — enforced in the
     *      controller, this just confirms the order is one of theirs).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $orderId = $this->input('order_id');
            $customerId = $this->input('customer_id');

            if (! $orderId) {
                return;
            }

            $order = Order::find($orderId);

            if (! $order) {
                return;
            }

            if ($customerId !== null && (int) $order->customer_id !== (int) $customerId) {
                $validator->errors()->add('order_id', 'هذا الطلب لا يعود لهذا العميل');
            }

            $amount = (float) $this->input('amount', 0);
            if ($amount > (float) $order->remaining_amount) {
                $validator->errors()->add(
                    'amount',
                    'المبلغ المُدخل يتجاوز المتبقي على الطلب ('.$order->remaining_amount.')'
                );
            }

            $user = $this->user();
            if ($user->agent) {
                if ($order->agent_id !== $user->agent->id) {
                    $validator->errors()->add('order_id', 'لا يمكنك تسجيل دفعة على طلب غير مرتبط بك');
                }

                if ($this->has('agent_id') && (int)$this->input('agent_id') !== $user->agent->id) {
                    $validator->errors()->add('agent_id', 'يجب أن تسجل الدفعة باسمك');
                }
            }
        });
    }
}
