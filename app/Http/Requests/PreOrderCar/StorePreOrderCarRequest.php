<?php

namespace App\Http\Requests\PreOrderCar;

use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class StorePreOrderCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PreOrderCar::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'finition' => ['nullable', 'string', 'max:255'],
            'manufacture_year' => ['required', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:255'],

            'price' => ['required', 'numeric', 'min:0'],
            'customs_fees' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
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
