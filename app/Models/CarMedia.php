<?php

namespace App\Models;

use App\Services\MediaUploadResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CarMedia extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = MediaUploadResolver::TYPE_IMAGE;

    public const TYPE_VIDEO = MediaUploadResolver::TYPE_VIDEO;

    protected $table = 'car_media';

    protected $fillable = [
        'car_id',
        'type',
        'url',
        'disk',
        'path',
        'mime_type',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Clean up the physical file on disk whenever a media row that
        // owns one is deleted. No-op for external-URL rows. Tag pivot
        // rows in `taggables` are cleaned up automatically by the DB
        // (cascadeOnDelete on tag_id doesn't cover this side, but Eloquent
        // has no cascading morphToMany delete — see note in README).
        static::deleting(function (CarMedia $media): void {
            MediaUploadResolver::deleteFile($media->disk, $media->path);
            $media->tags()->detach();
        });
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Tags attached to this specific image/video, shared with the same
     * `tags` table GeneralMedia uses.
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
