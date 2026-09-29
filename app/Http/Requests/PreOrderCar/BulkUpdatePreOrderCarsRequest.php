<?php

namespace App\Http\Requests\PreOrderCar;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePreOrderCarsRequest extends FormRequest
{
    private const UPDATE_FIELDS = [
        'customs_fees',
        'customs_fees_under_three',
        'preparation_days',
        'shipping_days',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('pre_order_cars.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pre_order_car_ids'                  => ['required', 'array', 'min:1'],
            'pre_order_car_ids.*'                => ['required', 'integer', 'distinct', 'exists:pre_order_cars,id'],
            'customs_fees'                       => ['sometimes', 'required', 'numeric', 'min:0'],
            'customs_fees_under_three'           => ['sometimes', 'required', 'numeric', 'min:0'],
            'preparation_days'                   => ['sometimes', 'required', 'string', 'max:255'],
            'shipping_days'                      => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $providedFields = array_intersect(array_keys($this->all()), self::UPDATE_FIELDS);

            if ($providedFields === []) {
                $validator->errors()->add(
                    'updates',
                    'يجب إرسال حقل واحد على الأقل من حقول التعديل المسموح بها'
                );
            }

            $allowedFields = array_merge(['pre_order_car_ids'], self::UPDATE_FIELDS);
            foreach (array_diff(array_keys($this->all()), $allowedFields) as $field) {
                $validator->errors()->add($field, 'هذا الحقل غير مسموح به في التعديل الجماعي');
            }
        });
    }

    /**
     * Return only the four fields that may be changed in bulk.
     *
     * @return array<string, mixed>
     */
    public function updateData(): array
    {
        return array_intersect_key(
            $this->validated(),
            array_flip(self::UPDATE_FIELDS)
        );
    }
}
