<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiningRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'dining_menu_id',
        'user_id',
        'score',
        'comment',
    ];

    public function menu()
    {
        return $this->belongsTo(DiningMenu::class, 'dining_menu_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
