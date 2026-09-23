<?php

namespace App\Http\Requests\Tag;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Tag::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:tags,name'],
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
