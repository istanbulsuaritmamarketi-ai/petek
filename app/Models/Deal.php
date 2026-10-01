<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'category',
        'category_label',
        'discount',
        'description',
        'promo_code',
        'url',
        'icon',
        'color',
        'verified',
        'expires_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];
}
