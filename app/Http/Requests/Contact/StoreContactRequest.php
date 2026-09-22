<?php

namespace App\Http\Requests\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contact::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Digits, an optional leading +, spaces and dashes allowed
            // (e.g. "+213 555 12 34 56") — kept loose since WhatsApp
            // numbers come in varied international formats.
            'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],

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
