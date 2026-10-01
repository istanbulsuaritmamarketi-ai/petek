<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department',
    ];

    public function ratings()
    {
        return $this->hasMany(ProfessorRating::class);
    }

    public function getAvgAttendanceAttribute()
    {
        return round($this->ratings()->avg('attendance') ?? 0, 1);
    }

    public function getAvgDifficultyAttribute()
    {
        return round($this->ratings()->avg('difficulty') ?? 0, 1);
    }

    public function getAvgTeachingAttribute()
    {
        return round($this->ratings()->avg('teaching') ?? 0, 1);
    }

    public function getRatingCountAttribute()
    {
        return $this->ratings()->count();
    }
}
