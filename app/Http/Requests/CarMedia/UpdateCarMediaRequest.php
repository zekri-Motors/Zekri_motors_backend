<?php

namespace App\Http\Requests\CarMedia;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('carMedia'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 'title' => ['nullable', 'string', 'max:255'],
            // 'is_cover' => ['nullable', 'boolean'],
            // 'sort_order' => ['nullable', 'integer', 'min:0'],

            // When present, REPLACES the media's full tag set (sync, not
            // append) — send the complete list the client wants, including
            // ones that should stay. Omit the field entirely to leave tags
            // untouched.
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
