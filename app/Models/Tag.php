<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function carMedia(): MorphToMany
    {
        return $this->morphedByMany(CarMedia::class, 'taggable');
    }

    public function generalMedia(): MorphToMany
    {
        return $this->morphedByMany(GeneralMedia::class, 'taggable');
    }
}
