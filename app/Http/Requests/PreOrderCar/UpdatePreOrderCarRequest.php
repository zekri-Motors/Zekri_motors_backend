<?php

namespace App\Http\Requests\PreOrderCar;

use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreOrderCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('preOrderCar'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'finition' => ['nullable', 'string', 'max:255'],
            'manufacture_year' => ['sometimes', 'required', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:255'],

            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'customs_fees' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('manufacture_year')) {
                return;
            }

            $year = (int) $this->input('manufacture_year');

            if (! PreOrderCar::isEligibleManufactureYear($year)) {
                $validator->errors()->add(
                    'manufacture_year',
                    'الطلب المسبق متاح فقط للسيارات الجديدة أو التي عمرها أقل من 3 سنوات'
                );
            }
        });
    }
}
