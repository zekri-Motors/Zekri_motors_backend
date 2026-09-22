<?php

namespace App\Http\Requests\PreOrderCar;

use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class ImportPreOrderCarsRequest extends FormRequest
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
            // Entered manually on the same screen as the file upload —
            // one supplier/container-opener for the whole sheet, same
            // pattern as the normal batch import.
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'container_opener_id' => ['nullable', 'integer', 'exists:container_openers,id'],
            'notes' => ['nullable', 'string'],

            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'يجب أن يكون الملف بصيغة xlsx أو xls أو csv',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }
}
