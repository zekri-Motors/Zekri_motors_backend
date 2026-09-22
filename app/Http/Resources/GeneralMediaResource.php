<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\GeneralMedia
 */
class GeneralMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'type' => $this->type,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,

            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,

            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),

            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
