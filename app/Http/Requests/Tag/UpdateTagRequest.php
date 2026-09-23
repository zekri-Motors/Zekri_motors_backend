<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tag'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tags', 'name')->ignore($this->route('tag')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم التاق إلزامي',
            'name.max' => 'اسم التاق طويل جدًا (الحد الأقصى 50 حرفًا)',
            'name.unique' => 'هذا التاق موجود مسبقًا',
        ];
    }
}
