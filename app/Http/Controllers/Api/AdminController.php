<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Post;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_posts' => Post::count(),
            'pending_reports' => DB::table('reports')->where('status', 'pending')->count(),
        ]);
    }

    public function users(Request $request)
    {
        $q = trim($request->get('q', ''));
        $query = User::latest();
        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('nickname', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('department', 'like', "%{$q}%")
                    ->orWhere('faculty', 'like', "%{$q}%");
            });
        }
        $users = $query->limit(100)->get();
        return response()->json($users->map(fn ($u) => [
            'id' => (string) $u->id,
            'nickname' => $u->nickname ?: $u->name,
            'name' => $u->name,
            'email' => $u->email,
            'department' => $u->department ?? 'İTÜ',
            'faculty' => $u->faculty ?? '',
            'role' => $u->role ?: 'student',
            'bee_points' => (int) ($u->bee_points ?? 0),
            'focus_minutes' => (int) ($u->focus_minutes ?? 0),
            'is_banned' => $u->role === 'banned' || (bool)$u->is_frozen,
            'is_frozen' => (bool)$u->is_frozen,
            'badges' => is_array($u->badges) ? $u->badges : [],
            'created_at' => $u->created_at ? $u->created_at->toISOString() : '',
        ]));
    }

    public function pendingPrices()
    {
        $zones = Zone::whereNotNull('price_list')->get();
        $result = [];
        foreach ($zones as $zone) {
            $list = $zone->price_list ?? [];
            foreach ($list as $item) {
                if (($item['status'] ?? '') === 'pending') {
                    $submitter = User::find($item['submitted_by'] ?? null);
                    $result[] = [
                        'zone_id' => (string) $zone->id,
                        'zone_name' => $zone->name,
                        'price_id' => $item['id'] ?? uniqid(),
                        'product_name' => $item['product_name'] ?? '',
                        'price' => (string) ($item['price'] ?? ''),
                        'location_hint' => $item['location_hint'] ?? '',
                        'suggested_by_nickname' => $submitter?->nickname ?? 'Anonim',
                        'created_at' => $item['created_at'] ?? now()->toISOString(),
                    ];
                }
            }
        }
        return response()->json($result);
    }

    public function reports()
    {
        $reports = \App\Models\Report::with(['reporter'])->latest()->limit(100)->get();
        return response()->json($reports->map(function ($r) {
            $post = null;
            if ($r->target_type === 'post') {
                $p = Post::with('user')->find($r->target_id);
                if ($p) {
                    $post = [
                        'id' => (string) $p->id,
                        'content' => $p->content,
                        'author_nickname' => $p->is_anonymous ? 'Anonim' : ($p->user?->nickname ?? 'Bilinmeyen'),
                        'author_id' => (string) $p->user_id,
                    ];
                }
            }
            return [
                'id' => (string) $r->id,
                'reporter_id' => (string) $r->reporter_id,
                'reporter_nickname' => $r->reporter?->nickname ?? 'Anonim',
                'target_id' => (string) $r->target_id,
                'target_type' => $r->target_type,
                'post' => $post,
                'reason' => $r->reason,
                'status' => $r->status,
                'action_taken' => $r->action_taken,
                'created_at' => $r->created_at ? $r->created_at->toISOString() : '',
            ];
        }));
    }

    public function handleReportAction(Request $request, $reportId)
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $action = $request->input('action'); // 'delete_post' or 'dismiss'
        $report = \App\Models\Report::findOrFail($reportId);

        if ($action === 'delete_post') {
            if ($report->target_type === 'post') {
                $post = Post::find($report->target_id);
                if ($post) {
                    $author = $post->user;
                    if ($author && $author->post_count > 0) {
                        $author->decrement('post_count');
                    }
                    \App\Models\Comment::where('post_id', $post->id)->delete();
                    $post->likes()->detach();
                    $post->delete();
                }
            }
            $report->status = 'resolved';
            $report->action_taken = 'Gönderi silindi';
        } else {
            $report->status = 'rejected';
            $report->action_taken = 'Reddedildi / Kapatıldı';
        }
        $report->save();

        return response()->json(['message' => 'Rapor işlemi tamamlandı']);
    }

    public function userAction(Request $request, $action, $userId)
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $targetUser = User::findOrFail($userId);

        // Kırılmaz Kural: ID 1 ve ID 3 dokunulmazlığı
        if (in_array((int)$targetUser->id, [1, 3]) && in_array($action, ['ban', 'demote', 'remove_moderator'])) {
            return response()->json(['detail' => 'Korumalı admin hesapları engellenemez veya yetkisi alınamaz.'], 403);
        }

        // Moderator restrictions:
        if ($actor->role === 'moderator') {
            if (in_array($action, ['promote', 'demote', 'make_moderator', 'remove_moderator', 'set_role'])) {
                return response()->json(['detail' => 'Moderatörler kullanıcı rol yetkilerini değiştiremez.'], 403);
            }
            if (in_array($targetUser->role, ['admin', 'moderator'])) {
                return response()->json(['detail' => 'Moderatörler yöneticilere ve diğer moderatörlere işlem yapamaz.'], 403);
            }
        }

        if ($action === 'ban') {
            $targetUser->role = 'banned';
            $targetUser->is_frozen = true;
        } elseif ($action === 'unban') {
            $targetUser->role = 'student';
            $targetUser->is_frozen = false;
        } elseif ($action === 'promote' || $action === 'make_moderator') {
            $targetUser->role = 'moderator';
        } elseif ($action === 'demote' || $action === 'remove_moderator') {
            $targetUser->role = 'student';
        } elseif ($action === 'set_role') {
            $role = $request->input('role', 'student');
            if (in_array($role, ['student', 'moderator', 'admin', 'banned'])) {
                $targetUser->role = $role;
                $targetUser->is_frozen = ($role === 'banned');
            }
        }
        $targetUser->save();

        return response()->json(['message' => "Kullanıcı durumu güncellendi: {$action}", 'user' => $targetUser]);
    }

    public function updateUser(Request $request, $id)
    {
        $actor = $request->user();
        if (!$actor || $actor->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'nullable|string|in:student,moderator,admin,banned',
            'is_frozen' => 'nullable|boolean',
            'bee_points' => 'nullable|integer|min:0',
            'focus_minutes' => 'nullable|integer|min:0',
            'badges' => 'nullable|array',
            'badges.*' => 'string',
            'bio' => 'nullable|string|max:500',
            'department' => 'nullable|string|max:150',
            'faculty' => 'nullable|string|max:150',
        ]);

        // Kırılmaz Kural: ID 1 ve ID 3 dokunulmazlığı
        if (in_array((int)$targetUser->id, [1, 3])) {
            if (isset($validated['role']) && $validated['role'] !== 'admin') {
                return response()->json(['detail' => 'Korumalı admin hesaplarının yönetici rolü değiştirilemez.'], 403);
            }
            if (!empty($validated['is_frozen'])) {
                return response()->json(['detail' => 'Korumalı admin hesapları engellenemez veya dondurulamaz.'], 403);
            }
        }

        if (isset($validated['role'])) {
            $targetUser->role = $validated['role'];
            if ($validated['role'] === 'banned') {
                $targetUser->is_frozen = true;
            } elseif ($validated['role'] === 'student' && !isset($validated['is_frozen'])) {
                $targetUser->is_frozen = false;
            }
        }

        if (isset($validated['is_frozen'])) {
            $targetUser->is_frozen = (bool)$validated['is_frozen'];
            if ($targetUser->is_frozen) {
                $targetUser->role = 'banned';
            } elseif ($targetUser->role === 'banned') {
                $targetUser->role = 'student';
            }
        }

        if (isset($validated['bee_points'])) {
            $targetUser->bee_points = (int)$validated['bee_points'];
        }

        if (isset($validated['focus_minutes'])) {
            $targetUser->focus_minutes = (int)$validated['focus_minutes'];
        }

        if (isset($validated['badges'])) {
            $targetUser->badges = array_values(array_unique($validated['badges']));
        }

        if (isset($validated['bio'])) {
            $targetUser->bio = $validated['bio'];
        }

        if (isset($validated['department'])) {
            $targetUser->department = $validated['department'];
        }

        if (isset($validated['faculty'])) {
            $targetUser->faculty = $validated['faculty'];
        }

        $targetUser->save();

        return response()->json([
            'message' => 'Kullanıcı özellikleri ve yetkileri başarıyla güncellendi.',
            'user' => $targetUser,
        ]);
    }

    public function managePrice(Request $request, $zoneId, $priceId)
    {
        $actor = $request->user();
        if (!$actor || !in_array($actor->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $action = $request->input('action') ?: $request->query('action', 'approve');
        $zone = Zone::findOrFail($zoneId);
        $prices = $zone->price_list ?? [];

        $updated = [];
        foreach ($prices as $p) {
            if (($p['id'] ?? '') === $priceId) {
                if ($action === 'approve') {
                    $p['status'] = 'approved';
                    $updated[] = $p;
                }
            } else {
                $updated[] = $p;
            }
        }
        $zone->price_list = $updated;
        $zone->save();

        return response()->json(['message' => 'Fiyat durumu güncellendi']);
    }

    public function getAdSettings()
    {
        $settings = \App\Models\AppSetting::get('ads_config', [
            'ads_enabled' => false,
            'ad_network' => 'custom',
            'admob_banner_id' => 'ca-app-pub-3940256099942544/6300978111',
            'admob_interstitial_id' => 'ca-app-pub-3940256099942544/1033173712',
            'custom_ad_title' => 'İTÜ Mezunlar Derneği & Petek Sponsorluğu',
            'custom_ad_text' => 'İTÜ’lü girişimcilerin projelerini keşfedin ve destekleyin!',
            'custom_ad_image' => '',
            'custom_ad_url' => 'https://itu.edu.tr',
        ]);

        return response()->json($settings);
    }

    public function updateAdSettings(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $validated = $request->validate([
            'ads_enabled' => 'required|boolean',
            'ad_network' => 'nullable|string',
            'admob_banner_id' => 'nullable|string',
            'admob_interstitial_id' => 'nullable|string',
            'custom_ad_title' => 'nullable|string',
            'custom_ad_text' => 'nullable|string',
            'custom_ad_image' => 'nullable|string',
            'custom_ad_url' => 'nullable|string',
        ]);

        \App\Models\AppSetting::set('ads_config', $validated, 'Uygulama reklam ve sponsorluk ayarları');

        return response()->json([
            'message' => 'Reklam ayarları başarıyla güncellendi.',
            'settings' => $validated,
        ]);
    }

    public function getPostCategories()
    {
        $categories = \App\Models\AppSetting::get('post_categories', [
            ['key' => 'general', 'label' => 'Genel', 'icon' => 'chatbubbles-outline', 'color' => '#FFB800'],
            ['key' => 'support', 'label' => 'Destek', 'icon' => 'hand-left-outline', 'color' => '#2196F3'],
            ['key' => 'confession', 'label' => 'İtiraf', 'icon' => 'glasses-outline', 'color' => '#A78BFA'],
        ]);

        return response()->json($categories);
    }

    public function updatePostCategories(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $categories = $request->input('categories', []);
        if (!is_array($categories) || empty($categories)) {
            return response()->json(['detail' => 'En az bir kategori tanımlanmalıdır.'], 422);
        }

        \App\Models\AppSetting::set('post_categories', $categories, 'Akış gönderi kategorileri');

        return response()->json([
            'message' => 'Kategoriler başarıyla güncellendi.',
            'categories' => $categories,
        ]);
    }

    private function generateRawNotifications($user): array
    {
        $notifications = [];

        $userPostIds = \App\Models\Post::where('user_id', $user->id)->pluck('id');
        $recentComments = \App\Models\Comment::with(['user', 'post'])
            ->whereIn('post_id', $userPostIds)
            ->where('user_id', '!=', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($recentComments as $comment) {
            $actorName = $comment->is_anonymous ? 'Anonim Arı' : ($comment->user?->nickname ?? 'Bir kullanıcı');
            $notifications[] = [
                'id' => 'comment-' . $comment->id,
                'type' => 'comment',
                'title' => 'Gönderine yeni yorum 💬',
                'body' => $actorName . ' gönderine yorum yaptı: "' . mb_substr($comment->content, 0, 45) . (mb_strlen($comment->content) > 45 ? '...' : '') . '"',
                'post_id' => (string) $comment->post_id,
                'user_id' => $comment->is_anonymous ? null : (string) $comment->user_id,
                'is_read' => false,
                'created_at' => $comment->created_at->toISOString(),
            ];
        }

        // Recent Likes on User's Posts
        if ($userPostIds->isNotEmpty()) {
            $recentLikes = \Illuminate\Support\Facades\DB::table('post_likes')
                ->join('users', 'post_likes.user_id', '=', 'users.id')
                ->join('posts', 'post_likes.post_id', '=', 'posts.id')
                ->whereIn('post_likes.post_id', $userPostIds)
                ->where('post_likes.user_id', '!=', $user->id)
                ->select(
                    'post_likes.id',
                    'post_likes.post_id',
                    'post_likes.user_id',
                    'post_likes.created_at',
                    'users.nickname as user_nickname',
                    'posts.content as post_content'
                )
                ->orderByDesc('post_likes.created_at')
                ->limit(15)
                ->get();

            foreach ($recentLikes as $like) {
                $notifications[] = [
                    'id' => 'like-' . $like->id,
                    'type' => 'like',
                    'title' => 'Yeni Beğeni ❤️',
                    'body' => ($like->user_nickname ?? 'Bir kullanıcı') . ' gönderini beğendi.',
                    'post_id' => (string) $like->post_id,
                    'user_id' => (string) $like->user_id,
                    'is_read' => false,
                    'created_at' => $like->created_at ? \Carbon\Carbon::parse($like->created_at)->toISOString() : now()->toISOString(),
                ];
            }
        }

        // Replies to User's Comments
        $userCommentIds = \App\Models\Comment::where('user_id', $user->id)->pluck('id');
        if ($userCommentIds->isNotEmpty()) {
            $recentReplies = \App\Models\Comment::with(['user', 'post'])
                ->whereIn('parent_id', $userCommentIds)
                ->where('user_id', '!=', $user->id)
                ->latest()
                ->limit(10)
                ->get();

            foreach ($recentReplies as $reply) {
                $actorName = $reply->is_anonymous ? 'Anonim Arı' : ($reply->user?->nickname ?? 'Bir kullanıcı');
                $notifications[] = [
                    'id' => 'reply-' . $reply->id,
                    'type' => 'comment',
                    'title' => 'Yorumuna yeni yanıt 💬',
                    'body' => $actorName . ' yorumuna yanıt verdi: "' . mb_substr($reply->content, 0, 45) . (mb_strlen($reply->content) > 45 ? '...' : '') . '"',
                    'post_id' => (string) $reply->post_id,
                    'user_id' => $reply->is_anonymous ? null : (string) $reply->user_id,
                    'is_read' => false,
                    'created_at' => $reply->created_at->toISOString(),
                ];
            }

            // Likes on User's Comments
            $recentCommentLikes = \Illuminate\Support\Facades\DB::table('comment_likes')
                ->join('users', 'comment_likes.user_id', '=', 'users.id')
                ->join('comments', 'comment_likes.comment_id', '=', 'comments.id')
                ->whereIn('comment_likes.comment_id', $userCommentIds)
                ->where('comment_likes.user_id', '!=', $user->id)
                ->select(
                    'comment_likes.id',
                    'comment_likes.user_id',
                    'comment_likes.comment_id',
                    'comment_likes.created_at',
                    'users.nickname as user_nickname',
                    'comments.post_id'
                )
                ->orderByDesc('comment_likes.created_at')
                ->limit(10)
                ->get();

            foreach ($recentCommentLikes as $cLike) {
                $notifications[] = [
                    'id' => 'clike-' . $cLike->id,
                    'type' => 'like',
                    'title' => 'Yorumuna Beğeni ❤️',
                    'body' => ($cLike->user_nickname ?? 'Bir kullanıcı') . ' yorumunu beğendi.',
                    'post_id' => (string) $cLike->post_id,
                    'user_id' => (string) $cLike->user_id,
                    'is_read' => false,
                    'created_at' => $cLike->created_at ? \Carbon\Carbon::parse($cLike->created_at)->toISOString() : now()->toISOString(),
                ];
            }
        }

        $recentFollowers = $user->followers()->latest('user_follows.created_at')->limit(5)->get();
        foreach ($recentFollowers as $follower) {
            $notifications[] = [
                'id' => 'follow-' . $follower->id,
                'type' => 'follow',
                'title' => 'Yeni Takipçi 🎉',
                'body' => $follower->nickname . ' seni takip etmeye başladı.',
                'user_id' => (string) $follower->id,
                'is_read' => true,
                'created_at' => $follower->pivot?->created_at ? $follower->pivot->created_at->toISOString() : now()->toISOString(),
            ];
        }

        // Dining (Yemekhane) Rate Notifications & Praised Menu Broadcasts
        $todayMenus = \App\Models\DiningMenu::whereDate('date', now()->toDateString())->get();

        foreach ($todayMenus as $dMenu) {
            $mainItem = $dMenu->items[1] ?? ($dMenu->items[0] ?? 'Günün Yemeği');
            $mealLabel = $dMenu->meal_type === 'dinner' ? 'akşam' : 'öğle';

            // 1. Admin Broadcast: "Menüyü Puanla" notification sent by admin at any hour
            if ($dMenu->notification_sent) {
                $notifCount = $dMenu->notification_count ?: 1;
                $notifTime = $dMenu->last_notified_at ?: $dMenu->updated_at;
                $notifications[] = [
                    'id' => 'dining-rate-' . $dMenu->id . '-v' . $notifCount,
                    'type' => 'dining',
                    'dining_id' => (string) $dMenu->id,
                    'meal_type' => $dMenu->meal_type,
                    'title' => $dMenu->notification_title ?: ("Günün " . ($dMenu->meal_type === 'dinner' ? 'Akşam' : 'Öğle') . " Menüsünü Puanla! 🍽️"),
                    'body' => $dMenu->notification_body ?: "Bugünkü {$mealLabel} menüsünü ({$mainItem}) denedin mi? Yemekhaneyi puanla, arı kovanına fikir ver! ⭐",
                    'is_read' => false,
                    'created_at' => $notifTime ? $notifTime->toISOString() : now()->toISOString(),
                ];
            }

            // 2. High rating praise (if 3+ votes and >= 4.0 average)
            if ($dMenu->rating_count >= 3 && $dMenu->average_rating >= 4.0) {
                $notifications[] = [
                    'id' => 'dining-praise-' . $dMenu->id,
                    'type' => 'dining',
                    'dining_id' => (string) $dMenu->id,
                    'meal_type' => $dMenu->meal_type,
                    'title' => 'Yemekhanede Ziyafet Var! ⭐ ' . number_format($dMenu->average_rating, 1),
                    'body' => "Bugün {$mealLabel} menüsü öğrenciler tarafından çok beğenildi ({$mainItem}). Kaçırma! 🍽️",
                    'is_read' => false,
                    'created_at' => $dMenu->updated_at ? $dMenu->updated_at->toISOString() : now()->toISOString(),
                ];
            }
        }

        // Admin Broadcast Notifications
        $broadcasts = \App\Models\BroadcastNotification::where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        foreach ($broadcasts as $b) {
            $notifications[] = [
                'id' => 'broadcast-' . $b->id,
                'type' => $b->type ?: 'announcement',
                'title' => $b->title,
                'body' => $b->message,
                'target_screen' => $b->target_screen,
                'target_url' => $b->target_url,
                'is_read' => false,
                'created_at' => $b->created_at ? $b->created_at->toISOString() : now()->toISOString(),
            ];
        }

        $notifications[] = [
            'id' => 'sys-welcome',
            'type' => 'system',
            'title' => 'Petek Kovanına Hoş Geldin! 🐝',
            'body' => 'Odaklanma oturumlarını tamamlayarak ve kampüste aktif kalarak Arı Puanı topla, rozetleri kap!',
            'is_read' => true,
            'created_at' => $user->created_at ? $user->created_at->toISOString() : now()->toISOString(),
        ];

        usort($notifications, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $notifications;
    }

    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $dismissed = $this->getUserDismissedNotifications($user);
        $notifications = $this->generateRawNotifications($user);

        // Filter out dismissed notification IDs
        $filtered = array_values(array_filter($notifications, fn($n) => !in_array((string)$n['id'], $dismissed)));

        return response()->json($filtered);
    }

    public function deleteNotification(Request $request, $id)
    {
        $user = $request->user();
        $dismissed = $this->getUserDismissedNotifications($user);
        $id = (string) $id;

        if (!in_array($id, $dismissed)) {
            $dismissed[] = $id;
            $this->saveUserDismissedNotifications($user, $dismissed);
        }

        return response()->json(['message' => 'Bildirim başarıyla silindi.']);
    }

    public function clearAllNotifications(Request $request)
    {
        $user = $request->user();
        $raw = $this->generateRawNotifications($user);
        $allIds = array_map(fn($n) => (string)$n['id'], $raw);
        $dismissed = $this->getUserDismissedNotifications($user);
        $merged = array_values(array_unique(array_merge($dismissed, $allIds)));

        $this->saveUserDismissedNotifications($user, $merged);

        return response()->json(['message' => 'Tüm bildirimler başarıyla silindi.']);
    }

    private function getUserDismissedNotifications($user): array
    {
        $list = [];
        try {
            if (isset($user->dismissed_notifications) && is_array($user->dismissed_notifications)) {
                $list = $user->dismissed_notifications;
            }
        } catch (\Throwable $e) {}

        // Also merge with cached dismissed list
        $cacheKey = "user_dismissed_notifs_{$user->id}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        if (is_array($cached)) {
            $list = array_values(array_unique(array_merge($list, $cached)));
        }

        return array_map('strval', $list);
    }

    private function saveUserDismissedNotifications($user, array $dismissed): void
    {
        try {
            $user->dismissed_notifications = $dismissed;
            $user->save();
        } catch (\Throwable $e) {}

        // Always also save to cache
        $cacheKey = "user_dismissed_notifs_{$user->id}";
        \Illuminate\Support\Facades\Cache::put($cacheKey, $dismissed, now()->addMonths(6));
    }

    public function getBeeTiers()
    {
        $defaultTiers = [
            ['level' => 1, 'minPoints' => 0, 'title' => 'Çaylak Arı', 'icon' => 'egg-outline', 'badgeColor' => '#9E9E9E', 'description' => 'Kovana yeni katılan taze arı.'],
            ['level' => 2, 'minPoints' => 50, 'title' => 'Çalışkan Arı', 'icon' => 'construct-outline', 'badgeColor' => '#4CAF50', 'description' => 'Kütüphanede petek ören arı.'],
            ['level' => 3, 'minPoints' => 150, 'title' => 'Kıdemli Arı', 'icon' => 'shield-checkmark-outline', 'badgeColor' => '#2196F3', 'description' => 'Kampüsün kurdu olmuş tecrübeli arı.'],
            ['level' => 4, 'minPoints' => 350, 'title' => 'Bilge Arı', 'icon' => 'school-outline', 'badgeColor' => '#9C27B0', 'description' => 'Petek kütüphanesinin efendisi.'],
            ['level' => 5, 'minPoints' => 750, 'title' => 'Kraliçe Arı', 'icon' => 'trophy', 'badgeColor' => '#FFB800', 'description' => 'Kovanın en zirvesindeki efsane.'],
        ];
        $tiers = \App\Models\AppSetting::get('bee_tiers', $defaultTiers);
        return response()->json($tiers ?: $defaultTiers);
    }

    public function updateBeeTiers(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $tiers = $request->input('tiers', []);
        if (empty($tiers) || !is_array($tiers)) {
            return response()->json(['detail' => 'Geçerli arı seviyeleri listesi gönderilmelidir.'], 422);
        }
        \App\Models\AppSetting::set('bee_tiers', $tiers, 'Arı puan basamakları ve rozet unvanları');
        return response()->json(['message' => 'Arı basamakları başarıyla güncellendi.', 'tiers' => $tiers]);
    }

    public function getHeaderBanner()
    {
        $defaultBanner = [
            'banner_enabled' => false,
            'banner_type' => 'text', // 'text' | 'image'
            'banner_text' => '',
            'banner_image_url' => '',
            'banner_link' => '',
            'banner_bg' => '#D4951A',
            'banner_text_color' => '#000000',
            'banner_icon' => 'megaphone-outline',
            'auto_dismiss_seconds' => 7,
        ];
        $banner = \App\Models\AppSetting::get('header_banner', $defaultBanner);
        return response()->json($banner ?: $defaultBanner);
    }

    public function updateHeaderBanner(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $payload = [
            'banner_enabled' => filter_var($request->input('banner_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'banner_type' => (string) $request->input('banner_type', 'text'),
            'banner_text' => (string) $request->input('banner_text', ''),
            'banner_image_url' => (string) $request->input('banner_image_url', ''),
            'banner_link' => (string) $request->input('banner_link', ''),
            'banner_bg' => (string) $request->input('banner_bg', '#D4951A'),
            'banner_text_color' => (string) $request->input('banner_text_color', '#000000'),
            'banner_icon' => (string) $request->input('banner_icon', 'megaphone-outline'),
            'auto_dismiss_seconds' => (int) $request->input('auto_dismiss_seconds', 7),
        ];
        \App\Models\AppSetting::set('header_banner', $payload, 'Akış tepe duyuru bannerı ayarları');
        return response()->json(['message' => 'Duyuru bannerı başarıyla kaydedildi.', 'banner' => $payload]);
    }

    public function getContactSettings()
    {
        $defaultContact = [
            'email' => 'destek@itu.edu.tr',
            'phone' => '+90 212 285 30 00',
            'whatsapp' => '+90 530 000 00 00',
            'instagram' => 'itupetek',
            'website' => 'https://petek.itu.edu.tr',
            'address' => 'İstanbul Teknik Üniversitesi, Ayazağa Kampüsü, Maslak / Sarıyer / İstanbul',
            'support_hours' => 'Hafta içi 09:00 - 18:00',
            'support_message' => 'Öneri, şikayet, topluluk iş birlikleri veya teknik destek için bize her zaman ulaşabilirsiniz.',
        ];
        $contact = \App\Models\AppSetting::get('contact_info', $defaultContact);
        return response()->json($contact ?: $defaultContact);
    }

    public function updateContactSettings(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $payload = [
            'email' => (string) $request->input('email', 'destek@itu.edu.tr'),
            'phone' => (string) $request->input('phone', ''),
            'whatsapp' => (string) $request->input('whatsapp', ''),
            'instagram' => (string) $request->input('instagram', ''),
            'website' => (string) $request->input('website', ''),
            'address' => (string) $request->input('address', ''),
            'support_hours' => (string) $request->input('support_hours', 'Hafta içi 09:00 - 18:00'),
            'support_message' => (string) $request->input('support_message', ''),
        ];

        \App\Models\AppSetting::set('contact_info', $payload, 'İletişim ve destek bilgileri');
        return response()->json(['message' => 'İletişim bilgileri başarıyla güncellendi.', 'contact' => $payload]);
    }

    public function getBrandingSettings()
    {
        $defaultBranding = [
            'app_name' => 'PETEK',
            'app_title' => 'PETEK — İTÜ Öğrenci Kovanı',
            'favicon_url' => '/branding/petek-gold.png',
            'icon_url' => '/branding/petek-gold.png',
            'logo_url' => '/branding/petek-emblem.png',
            'active_preset' => 'petek_gold',
            'theme_color' => '#FDB913',
            'presets' => [
                [
                    'id' => 'petek_gold',
                    'name' => 'Altın Bal Petek (Minimalist)',
                    'url' => '/branding/petek-gold.png',
                    'favicon' => '/branding/petek-gold.png',
                    'description' => 'Işıltılı altın petek ve arı sembolü'
                ],
                [
                    'id' => 'petek_emblem',
                    'name' => 'PETEK İTÜ Amblem & Logo',
                    'url' => '/branding/petek-emblem.png',
                    'favicon' => '/branding/petek-emblem.png',
                    'description' => 'Resmi PETEK İTÜ yazılı arma'
                ],
            ]
        ];

        $branding = \App\Models\AppSetting::get('branding_settings', $defaultBranding);
        return response()->json($branding ?: $defaultBranding);
    }

    public function updateBrandingSettings(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['detail' => 'Bu işlem için admin yetkisi gerekiyor.'], 403);
        }

        $activePreset = (string) $request->input('active_preset', 'petek_gold');
        $appName = (string) $request->input('app_name', 'PETEK');
        $appTitle = (string) $request->input('app_title', 'PETEK — İTÜ Öğrenci Kovanı');
        $themeColor = (string) $request->input('theme_color', '#FDB913');

        $faviconUrl = '/branding/petek-gold.png';
        $iconUrl = '/branding/petek-gold.png';
        $logoUrl = '/branding/petek-emblem.png';

        if ($activePreset === 'petek_emblem') {
            $iconUrl = '/branding/petek-emblem.png';
            $faviconUrl = '/branding/petek-emblem.png';
            if (file_exists(public_path('branding/petek-emblem.png'))) {
                @copy(public_path('branding/petek-emblem.png'), public_path('favicon.png'));
            }
        } elseif ($activePreset === 'custom') {
            $customUrl = (string) $request->input('icon_url', '');
            $imageBase64 = $request->input('image_base64');

            if ($imageBase64) {
                $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64);
                $decoded = base64_decode($base64Data);
                if ($decoded) {
                    $fileName = 'custom-icon-' . time() . '.png';
                    $dir = public_path('uploads/branding');
                    if (!file_exists($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $filePath = $dir . '/' . $fileName;
                    file_put_contents($filePath, $decoded);
                    $iconUrl = '/uploads/branding/' . $fileName;
                    $faviconUrl = $iconUrl;

                    @file_put_contents(public_path('favicon.png'), $decoded);
                }
            } elseif ($customUrl) {
                $iconUrl = $customUrl;
                $faviconUrl = $customUrl;
            }
        } else {
            $iconUrl = '/branding/petek-gold.png';
            $faviconUrl = '/branding/petek-gold.png';
            if (file_exists(public_path('branding/petek-gold.png'))) {
                @copy(public_path('branding/petek-gold.png'), public_path('favicon.png'));
            }
        }

        $payload = [
            'app_name' => $appName,
            'app_title' => $appTitle,
            'favicon_url' => $faviconUrl,
            'icon_url' => $iconUrl,
            'logo_url' => $logoUrl,
            'active_preset' => $activePreset,
            'theme_color' => $themeColor,
            'presets' => [
                [
                    'id' => 'petek_gold',
                    'name' => 'Altın Bal Petek (Minimalist)',
                    'url' => '/branding/petek-gold.png',
                    'favicon' => '/branding/petek-gold.png',
                    'description' => 'Işıltılı altın petek ve arı sembolü'
                ],
                [
                    'id' => 'petek_emblem',
                    'name' => 'PETEK İTÜ Amblem & Logo',
                    'url' => '/branding/petek-emblem.png',
                    'favicon' => '/branding/petek-emblem.png',
                    'description' => 'Resmi PETEK İTÜ yazılı arma'
                ],
            ]
        ];

        \App\Models\AppSetting::set('branding_settings', $payload, 'Uygulama ikonu ve marka ayarları');
        return response()->json(['message' => 'Uygulama ikonu ve marka ayarları başarıyla güncellendi.', 'branding' => $payload]);
    }

    public function getBroadcastNotifications(Request $request)
    {
        $broadcasts = \App\Models\BroadcastNotification::with('sender:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($broadcasts);
    }

    public function sendBroadcastNotification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'message' => 'required|string|max:1000',
            'type' => 'nullable|string|max:50',
            'target_screen' => 'nullable|string|max:100',
            'target_url' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $broadcast = \App\Models\BroadcastNotification::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'announcement',
            'target_screen' => $validated['target_screen'] ?? null,
            'target_url' => $validated['target_url'] ?? null,
            'sent_by_user_id' => $user ? $user->id : null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Bildirim tüm İTÜ öğrencilerine başarıyla yayınlandı! 📢',
            'broadcast' => $broadcast->load('sender:id,name,nickname,avatar'),
        ], 201);
    }

    public function deleteBroadcastNotification(Request $request, $id)
    {
        $broadcast = \App\Models\BroadcastNotification::findOrFail($id);
        $broadcast->delete();

        return response()->json([
            'message' => 'Yayınlanan bildirim başarıyla silindi ve yayından kaldırıldı.',
        ]);
    }
}

