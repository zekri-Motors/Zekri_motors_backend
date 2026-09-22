<?php

namespace App\Services;

use App\Models\Tag;

/**
 * Turns an array of raw tag name strings (as submitted by the client)
 * into tag IDs, creating any tag that doesn't exist yet. Shared by
 * CarMediaController and GeneralMediaController so both media tables use
 * the exact same tags table/behaviour instead of each rolling their own.
 */
class TagResolver
{
    /**
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public static function resolveIds(array $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();
    }
}
