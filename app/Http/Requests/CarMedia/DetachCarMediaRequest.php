<?php

namespace App\Http\Requests\CarMedia;

use App\Models\CarMedia;
use Illuminate\Foundation\Http\FormRequest;

class DetachCarMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('carMedia');

        return $media instanceof CarMedia
            && $this->user()?->can('delete', $media) === true;
    }

    /**
     * The same request is used by both nested endpoints:
     * /cars/{car}/media/{carMedia} and
     * /pre-order-cars/{preOrderCar}/media/{carMedia}.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $media = $this->route('carMedia');
        $car = $this->route('car');
        $preOrderCar = $this->route('preOrderCar');

        if (! $media instanceof CarMedia) {
            return;
        }

        $attached = false;

        if ($car !== null) {
            $attached = $car->media()->whereKey($media->getKey())->exists();
        } elseif ($preOrderCar !== null) {
            $attached = $preOrderCar->media()->whereKey($media->getKey())->exists();
        }

        if (! $attached) {
            $this->merge(['_media_not_attached' => true]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('_media_not_attached')) {
                $validator->errors()->add(
                    'car_media_id',
                    'الميديا غير مرتبطة بهذه السيارة'
                );
            }
        });
    }
}
