<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'event_date',
        'location',
        'organizer_club',
        'image',
        'link',
        'fee',
        'is_club_event',
        'club_id',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'is_club_event' => 'boolean',
    ];

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
