<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    use HasFactory;

    protected $table = 'post_media';

    protected $fillable = [
        'post_id',
        'type',
        'url',
        'duration',
        'thumbnail',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
