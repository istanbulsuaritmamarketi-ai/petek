<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiningMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'meal_type',
        'title',
        'items',
        'calories',
        'vegetarian_items',
        'rating_sum',
        'rating_count',
        'average_rating',
        'notification_sent',
    ];

    protected $casts = [
        'items' => 'array',
        'vegetarian_items' => 'array',
        'average_rating' => 'float',
        'notification_sent' => 'boolean',
        'date' => 'date',
    ];

    public function ratings()
    {
        return $this->hasMany(DiningRating::class);
    }
}
