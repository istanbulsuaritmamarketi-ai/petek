<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessorRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'professor_id',
        'user_id',
        'attendance',
        'difficulty',
        'teaching',
        'comment',
    ];

    protected $casts = [
        'attendance' => 'integer',
        'difficulty' => 'integer',
        'teaching' => 'integer',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
