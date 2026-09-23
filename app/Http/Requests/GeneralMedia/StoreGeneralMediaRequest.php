<?php

namespace App\Http\Requests\GeneralMedia;

use App\Http\Requests\Concerns\ValidatesMediaSource;
use App\Models\GeneralMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeneralMediaRequest extends FormRequest
{
    use ValidatesMediaSource;

    public function authorize(): bool
    {
        return $this->user()->can('create', GeneralMedia::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm,mkv', 'max:102400'],
            'url' => ['nullable', 'url', 'max:2048'],

            'type' => ['nullable', Rule::in([GeneralMedia::TYPE_IMAGE, GeneralMedia::TYPE_VIDEO])],

            // 'title' => ['nullable', 'string', 'max:255'],
            // 'description' => ['nullable', 'string'],
            // 'sort_order' => ['nullable', 'integer', 'min:0'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($v) => $this->ensureExactlyOneMediaSource($v));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'صيغة الملف غير مدعومة (صور: jpg, png, gif, webp — فيديو: mp4, mov, avi, webm, mkv)',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 100 ميجابايت',
            'url.url' => 'الرابط غير صالح',
            'tags.*.max' => 'اسم التاق طويل جدًا (الحد الأقصى 50 حرفًا)',
        ];
    }
}
