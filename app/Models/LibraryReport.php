<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'floor_id',
        'user_id',
        'occupancy_level',
        'socket_level',
        'noise_level',
        'comment',
    ];

    public function floor()
    {
        return $this->belongsTo(LibraryFloor::class, 'floor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
