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
        'last_notified_at',
        'notification_title',
        'notification_body',
        'notification_count',
    ];

    protected $casts = [
        'items' => 'array',
        'vegetarian_items' => 'array',
        'average_rating' => 'float',
        'notification_sent' => 'boolean',
        'last_notified_at' => 'datetime',
        'notification_count' => 'integer',
        'date' => 'date',
    ];

    public function ratings()
    {
        return $this->hasMany(DiningRating::class);
    }
}
