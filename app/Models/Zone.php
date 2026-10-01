<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'campus',
        'building',
        'floor',
        'description',
        'directions',
        'map_url',
        'opening_hours',
        'is_active',
        'phone',
        'image',
        'lat',
        'lng',
        'radius',
        'capacity',
        'socket_status',
        'noise_level',
        'wifi_quality',
        'amenities',
        'zone_type',
        'has_menu',
        'price_list',
        'suggestions',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'has_menu' => 'boolean',
        'price_list' => 'array',
        'suggestions' => 'array',
        'amenities' => 'array',
    ];

    public function votes()
    {
        return $this->hasMany(ZoneVote::class);
    }

    public function recentVotes()
    {
        return $this->hasMany(ZoneVote::class)->where('created_at', '>=', now()->subMinutes(30));
    }
}
