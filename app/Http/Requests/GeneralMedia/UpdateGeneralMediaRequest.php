<?php

namespace App\Http\Requests\GeneralMedia;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('generalMedia'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // When present, REPLACES the media's full tag set (sync).
            // Omit the field entirely to leave tags untouched.
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
