<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'reply_to_user_id',
        'content',
        'is_anonymous',
        'view_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'view_count' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replyToUser()
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_likes')->withTimestamps();
    }
}
