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
            'media_id' => ['nullable', 'integer', 'exists:car_media,id'],
            'media_ids' => ['nullable', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'distinct', 'exists:car_media,id'],
            // Exactly one of these two — enforced in withValidator() below.
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm,mkv', 'max:102400'],
            // Accept one URL for backwards compatibility, or an array of
            // external image/video URLs to attach in one request.
            'url' => ['nullable', function ($attribute, $value, $fail): void {
                if (is_array($value)) {
                    if ($value === []) {
                        $fail('يجب إرسال رابط واحد على الأقل');
                    }

                    return;
                }

                if (! filter_var($value, FILTER_VALIDATE_URL) || strlen($value) > 2048) {
                    $fail('الرابط غير صالح');
                }
            }],
            'url.*' => ['required', 'url', 'max:2048'],

            // Required when 'url' is used; optional (auto-guessed) with 'file'.
            'type' => ['nullable', Rule::in([CarMedia::TYPE_IMAGE, CarMedia::TYPE_VIDEO])],

            'title' => ['nullable', 'string', 'max:255'],
            'is_cover' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // Tag names, e.g. ["خارجية", "محرك"]. Unknown names are
            // created only when the user has tags.create.
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $ids = array_filter(array_merge(
                $this->input('media_ids', []),
                $this->filled('media_id') ? [$this->input('media_id')] : [],
            ));

            if ($ids !== []) {
                if ($this->hasFile('file') || $this->filled('url')) {
                    $v->errors()->add('media_id', 'لا يمكن إرسال ميديا موجودة مع ملف أو رابط جديد');
                }

                return;
            }

            $this->ensureExactlyOneMediaSource($v);
        });
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
            'url.*.url' => 'أحد الروابط غير صالح',
            'tags.*.max' => 'اسم التاق طويل جدًا (الحد الأقصى 50 حرفًا)',
        ];
    }
}
