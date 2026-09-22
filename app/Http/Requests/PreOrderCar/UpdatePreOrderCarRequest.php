<?php

namespace App\Http\Requests\PreOrderCar;

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
            // supplier_id is intentionally not editable here — changing the
            // supplier after creation would leave the car's origin
            // inconsistent with how it was imported/grouped.
            'container_opener_id' => ['nullable', 'integer', 'exists:container_openers,id'],

            'brand' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'finition' => ['nullable', 'string', 'max:255'],
            'manufacture_year' => ['sometimes', 'required', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:255'],

            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
