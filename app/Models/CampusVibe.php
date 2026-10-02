<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class CampusVibe extends Model
{
    use HasFactory, Prunable;

    protected $fillable = [
        'user_id',
        'emoji',
        'content',
        'location_name',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Vibelar 24 saat sonra veritabanından otomatik olarak temizlenir.
     */
    public function prunable()
    {
        return static::where('expires_at', '<=', now())
            ->orWhere('created_at', '<=', now()->subHours(24));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
