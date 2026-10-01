<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryFloor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'floor_code',
        'building',
        'capacity',
        'occupancy_rate',
        'socket_status',
        'noise_level',
        'wifi_quality',
        'report_count',
        'last_reported_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'occupancy_rate' => 'integer',
        'report_count' => 'integer',
        'last_reported_at' => 'datetime',
    ];

    public function reports()
    {
        return $this->hasMany(LibraryReport::class, 'floor_id');
    }

    public function recentReports()
    {
        return $this->reports()->where('created_at', '>=', now()->subMinutes(60));
    }
}
