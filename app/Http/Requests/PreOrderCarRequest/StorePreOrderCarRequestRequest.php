<?php

namespace App\Http\Requests\PreOrderCarRequest;

use App\Models\Contact;
use App\Models\PreOrderCar;
use Illuminate\Foundation\Http\FormRequest;

class StorePreOrderCarRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var PreOrderCar|null $preOrderCar */
            $preOrderCar = $this->route('preOrderCar');

            if (! $preOrderCar || ! $preOrderCar->isPending()) {
                $validator->errors()->add('pre_order_car', 'هذه السيارة غير متاحة للطلب المسبق حاليًا');

                return;
            }

            $whatsapp = preg_replace('/[\s\-]/', '', (string) $this->input('whatsapp_number'));

            $contactIds = Contact::query()
                ->where('whatsapp_number', $this->input('whatsapp_number'))
                ->orWhere('whatsapp_number', $whatsapp)
                ->pluck('id');

            if ($contactIds->isNotEmpty()
                && $preOrderCar->requests()->whereIn('contact_id', $contactIds)->exists()) {
                $validator->errors()->add('whatsapp_number', 'تم تقديم طلب مسبق على هذه السيارة بهذا الرقم مسبقًا');
            }
        });
    }
}
