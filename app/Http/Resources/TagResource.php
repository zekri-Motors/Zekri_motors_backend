<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'car_media_count' => $this->whenCounted('carMedia'),
            'general_media_count' => $this->whenCounted('generalMedia'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
