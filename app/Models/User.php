<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'department',
        'faculty',
        'role',
        'is_frozen',
        'account_type',
        'badges',
        'bee_points',
        'avatar',
        'bio',
        'post_count',
        'focus_minutes',
        'location_lat',
        'location_lng',
        'location_updated_at',
        'club_name',
        'club_description',
        'club_category',
        'owner_id',
        'course_schedule',
        'dismissed_notifications',
        'share_campus_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_frozen' => 'boolean',
            'share_campus_status' => 'boolean',
            'badges' => 'array',
            'dismissed_notifications' => 'array',
            'course_schedule' => 'array',
            'location_updated_at' => 'datetime',
            'bee_points' => 'integer',
            'post_count' => 'integer',
            'focus_minutes' => 'integer',
            'owner_id' => 'integer',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function managedClubs()
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likedPosts()
    {
        return $this->belongsToMany(Post::class, 'post_likes')->withTimestamps();
    }

    public function likedComments()
    {
        return $this->belongsToMany(Comment::class, 'comment_likes')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function ratings()
    {
        return $this->hasMany(ProfessorRating::class);
    }

    public function focusSessions()
    {
        return $this->hasMany(FocusSession::class);
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')->withTimestamps();
    }

    public function blockedUsers()
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'user_id', 'blocked_id')->withTimestamps();
    }

    public function blockedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'user_id')->withTimestamps();
    }

    /**
     * Determines whether the user is currently verified to be on campus.
     */
    public function isOnCampus(): bool
    {
        // 1. If user disabled sharing campus status, always false
        if ($this->share_campus_status === false) {
            return false;
        }

        // 2. Must have coordinates and an updated timestamp
        if (!$this->location_lat || !$this->location_lng || !$this->location_updated_at) {
            return false;
        }

        // 3. Must be updated within the last 120 minutes (2 hours)
        if ($this->location_updated_at->lt(now()->subMinutes(120))) {
            return false;
        }

        $lat = (float) $this->location_lat;
        $lng = (float) $this->location_lng;

        // 4. Ayazağa Kampüsü (Maslak)
        if ($lat >= 41.0950 && $lat <= 41.1180 && $lng >= 29.0120 && $lng <= 29.0400) {
            return true;
        }

        // 5. Taşkışla, Gümüşsuyu, Maçka Kampüsleri (Şehir Kampüsleri)
        if ($lat >= 41.0340 && $lat <= 41.0500 && $lng >= 28.9800 && $lng <= 29.0050) {
            return true;
        }

        // 6. Tuzla Kampüsü (Denizcilik Fakültesi)
        if ($lat >= 40.8100 && $lat <= 40.8250 && $lng >= 29.2900 && $lng <= 29.3100) {
            return true;
        }

        // 7. Proximity to any registered Zone in zones table (~500 meters)
        return Zone::whereBetween('lat', [$lat - 0.005, $lat + 0.005])
            ->whereBetween('lng', [$lng - 0.005, $lng + 0.005])
            ->exists();
    }

    /**
     * Get user-friendly campus status info.
     */
    public function getCampusStatus(): array
    {
        $isOn = $this->isOnCampus();
        if (!$isOn) {
            return [
                'is_on_campus' => false,
                'status_text' => 'Kampüs Dışında',
                'campus_name' => null,
            ];
        }

        $lat = (float) $this->location_lat;
        $lng = (float) $this->location_lng;

        $campusName = 'İTÜ Kampüsünde';
        if ($lat >= 41.0950 && $lat <= 41.1180 && $lng >= 29.0120 && $lng <= 29.0400) {
            $campusName = 'Ayazağa Kampüsü';
        } elseif ($lat >= 41.0340 && $lat <= 41.0500 && $lng >= 28.9800 && $lng <= 29.0050) {
            $campusName = 'Şehir Kampüsü';
        } elseif ($lat >= 40.8100 && $lat <= 40.8250 && $lng >= 29.2900 && $lng <= 29.3100) {
            $campusName = 'Tuzla Kampüsü';
        }

        return [
            'is_on_campus' => true,
            'status_text' => 'Kampüste',
            'campus_name' => $campusName,
        ];
    }

    public function getAvatarAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Clean URL
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/uploads/') || str_starts_with($value, 'uploads/')) {
            return $value;
        }

        // Base64 data URI: check if valid image; if corrupted (e.g. truncated at 1MB), return null
        if (str_starts_with($value, 'data:image') || str_starts_with($value, 'data:application')) {
            $commaPos = strpos($value, ',');
            if ($commaPos === false) {
                return null;
            }
            $raw = substr($value, $commaPos + 1);
            $decoded = base64_decode($raw, true);
            if ($decoded === false || strlen($decoded) < 100) {
                return null;
            }
            if (function_exists('getimagesizefromstring') && !@getimagesizefromstring($decoded)) {
                return null;
            }
            return $value;
        }

        return $value;
    }
}
