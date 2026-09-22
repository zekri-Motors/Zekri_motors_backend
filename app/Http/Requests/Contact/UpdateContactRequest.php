<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contact'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'whatsapp_number' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم جهة الاتصال إلزامي',
            'whatsapp_number.required' => 'رقم الواتساب إلزامي',
            'whatsapp_number.regex' => 'رقم الواتساب غير صالح',
        ];
    }
}
