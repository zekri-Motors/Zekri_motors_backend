<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Shared between CarMedia and GeneralMedia: resolves either an uploaded
 * file OR an external URL into the same set of attributes, and knows how
 * to delete a stored file when its media row is removed. Keeps the two
 * media flows (car-specific / general) from duplicating this logic.
 */
class MediaUploadResolver
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    /**
     * @return array{type:string,url:string,disk:?string,path:?string,mime_type:?string,size:?int}
     */
    public static function resolve(?UploadedFile $file, ?string $externalUrl, ?string $typeInput, string $folder, string $disk = 'public'): array
    {
        if ($file !== null) {
            $path = $file->store($folder, $disk);
            $mime = $file->getMimeType();

            return [
                'type' => $typeInput ?? self::guessTypeFromMime($mime),
                'url' => Storage::disk($disk)->url($path),
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ];
        }

        // External URL: nothing of ours to store or clean up later.
        return [
            'type' => $typeInput,
            'url' => $externalUrl,
            'disk' => null,
            'path' => null,
            'mime_type' => null,
            'size' => null,
        ];
    }

    public static function guessTypeFromMime(?string $mime): string
    {
        if ($mime !== null && str_starts_with($mime, 'video/')) {
            return self::TYPE_VIDEO;
        }

        return self::TYPE_IMAGE;
    }

    /**
     * Delete the underlying stored file, if this media row actually owns
     * one (an external-URL row has disk/path both NULL, nothing to do).
     */
    public static function deleteFile(?string $disk, ?string $path): void
    {
        if ($disk !== null && $path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
