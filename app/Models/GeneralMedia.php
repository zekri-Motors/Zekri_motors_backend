<?php

namespace App\Models;

use App\Services\MediaUploadResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class GeneralMedia extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = MediaUploadResolver::TYPE_IMAGE;

    public const TYPE_VIDEO = MediaUploadResolver::TYPE_VIDEO;

    protected $table = 'general_media';

    protected $fillable = [
        'type',
        'url',
        'disk',
        'path',
        'mime_type',
        'size',
        'title',
        'description',
        'sort_order',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (GeneralMedia $media): void {
            MediaUploadResolver::deleteFile($media->disk, $media->path);
            $media->tags()->detach();
        });
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Tags attached to this specific image/video, shared with the same
     * `tags` table CarMedia uses.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }
}
