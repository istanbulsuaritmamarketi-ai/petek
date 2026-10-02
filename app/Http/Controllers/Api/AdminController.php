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

    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $notifications = [];

        $userPostIds = \App\Models\Post::where('user_id', $user->id)->pluck('id');
        $recentComments = \App\Models\Comment::with(['user', 'post'])
            ->whereIn('post_id', $userPostIds)
            ->where('user_id', '!=', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($recentComments as $comment) {
            $notifications[] = [
                'id' => 'comment-' . $comment->id,
                'type' => 'comment',
                'title' => 'Gönderine yeni yorum',
                'body' => ($comment->is_anonymous ? 'Anonim Arı' : ($comment->user?->nickname ?? 'Bir kullanıcı')) . ': "' . mb_substr($comment->content, 0, 40) . '..."',
                'post_id' => (string) $comment->post_id,
                'is_read' => false,
                'created_at' => $comment->created_at->toISOString(),
            ];
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

        // Dining (Yemekhane) Good Food Notification - ONLY when real students vote (at least 3 ratings and average >= 4.0)
        $todayMenu = \App\Models\DiningMenu::whereDate('date', now()->toDateString())
            ->where('rating_count', '>=', 3)
            ->where('average_rating', '>=', 4.0)
            ->latest('average_rating')
            ->first();

        if ($todayMenu) {
            $mainItem = $todayMenu->items[1] ?? ($todayMenu->items[0] ?? 'Günün Yemeği');
            $mealLabel = $todayMenu->meal_type === 'dinner' ? 'akşam' : 'öğle';
            $notifications[] = [
                'id' => 'dining-' . $todayMenu->id,
                'type' => 'dining',
                'title' => 'Yemekhanede Ziyafet Var! ⭐ ' . number_format($todayMenu->average_rating, 1),
                'body' => "Bugün {$mealLabel} menüsü öğrenciler tarafından çok beğenildi ({$mainItem}). Kaçırma! 🍽️",
                'is_read' => false,
                'created_at' => $todayMenu->updated_at ? $todayMenu->updated_at->toISOString() : now()->toISOString(),
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

        return response()->json($notifications);
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
            'banner_bg' => '#FFB800',
            'banner_text_color' => '#000000',
            'banner_icon' => 'megaphone-outline',
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
            'banner_bg' => (string) $request->input('banner_bg', '#FFB800'),
            'banner_text_color' => (string) $request->input('banner_text_color', '#000000'),
            'banner_icon' => (string) $request->input('banner_icon', 'megaphone-outline'),
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
}

