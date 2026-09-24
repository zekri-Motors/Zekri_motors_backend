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
