<?php

namespace App\Http\Requests\CarMedia;

use App\Http\Requests\Concerns\ValidatesMediaSource;
use App\Models\CarMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarMediaRequest extends FormRequest
{
    use ValidatesMediaSource;

    public function authorize(): bool
    {
        return $this->user()->can('create', CarMedia::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Exactly one of these two — enforced in withValidator() below.
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm,mkv', 'max:102400'],
            'url' => ['nullable', 'url', 'max:2048'],

            // Required when 'url' is used; optional (auto-guessed) with 'file'.
            'type' => ['nullable', Rule::in([CarMedia::TYPE_IMAGE, CarMedia::TYPE_VIDEO])],

            'title' => ['nullable', 'string', 'max:255'],
            'is_cover' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // Tag names, e.g. ["خارجية", "محرك"]. Unknown tags are
            // created automatically — no separate "create tag" step needed.
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
