<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'reason',
        'content_snippet',
        'matched_terms',
        'ip_address',
    ];

    protected $casts = [
        'matched_terms' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
