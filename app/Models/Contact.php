<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'whatsapp_number',
        'address',
    ];

    public function preOrderCarRequests(): HasMany
    {
        return $this->hasMany(PreOrderCarRequest::class);
    }
}
