<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'post_type',
        'is_anonymous',
        'hashtags',
        'view_count',
        'is_archived',
        'is_pinned',
        'pinned_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_archived' => 'boolean',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'hashtags' => 'array',
        'view_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->orderBy('created_at', 'asc');
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class);
    }
}
