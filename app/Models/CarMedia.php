<?php

namespace App\Models;

use App\Services\MediaUploadResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CarMedia extends Model
{
    use HasFactory;

    public const TYPE_IMAGE = MediaUploadResolver::TYPE_IMAGE;

    public const TYPE_VIDEO = MediaUploadResolver::TYPE_VIDEO;

    protected $table = 'car_media';

    protected $fillable = [
        'type',
        'url',
        'disk',
        'path',
        'uploaded_by',
        'title',
    ];

    protected function casts(): array
    {
        return [
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

    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'car_media_links')
            ->withPivot(['is_cover', 'sort_order'])
            ->withTimestamps();
    }

    public function preOrderCars(): BelongsToMany
    {
        return $this->belongsToMany(PreOrderCar::class, 'car_media_links', 'car_media_id', 'pre_order_car_id')
            ->withPivot(['is_cover', 'sort_order'])
            ->withTimestamps();
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
