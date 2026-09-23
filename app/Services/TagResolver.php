<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Turns an array of raw tag name strings (as submitted by the client)
 * into tag IDs. Shared by CarMediaController and GeneralMediaController.
 *
 * Existing tags are always reused. A missing name is created only when
 * the acting user is allowed to create tags (TagPolicy::create /
 * tags.create). Otherwise attaching unknown names is rejected so media
 * endpoints cannot bypass the tag CRUD permissions.
 */
class TagResolver
{
    /**
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public static function resolveIds(array $names, ?User $user = null): array
    {
        $user ??= auth()->user();
        $canCreate = $user?->can('create', Tag::class) ?? false;

        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->map(function ($name) use ($canCreate) {
                $tag = Tag::query()->where('name', $name)->first();

                if ($tag) {
                    return $tag->id;
                }

                if (! $canCreate) {
                    throw ValidationException::withMessages([
                        'tags' => "التاق \"{$name}\" غير موجود. يجب إنشاؤه أولاً من إدارة التاقات",
                    ]);
                }

                return Tag::create(['name' => $name])->id;
            })
            ->values()
            ->all();
    }
}
