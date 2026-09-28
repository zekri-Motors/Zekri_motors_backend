<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\PreOrderCarRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for PUT /pre-order-car-requests/{preOrderCarRequest} (flat route).
 */
class UpdatePreOrderCarRequestDirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PreOrderCarRequest|null $preOrderCarRequest */
        $preOrderCarRequest = $this->route('preOrderCarRequest');

        return $this->user()->can('update', $preOrderCarRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    PreOrderCarRequest::STATUS_DRAFT,
                    PreOrderCarRequest::STATUS_PENDING,
                    PreOrderCarRequest::STATUS_COMPLETED,
                ]),
            ],
            'notes'       => ['sometimes', 'nullable', 'string'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in'          => 'الحالة يجب أن تكون: draft أو pending أو completed',
            'customer_id.exists' => 'العميل المحدد غير موجود في النظام',
        ];
    }
}
