<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BroadcastNotification extends Model
{
    use HasFactory;

    protected $table = 'broadcast_notifications';

    protected $fillable = [
        'title',
        'message',
        'type',
        'target_screen',
        'target_url',
        'sent_by_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
