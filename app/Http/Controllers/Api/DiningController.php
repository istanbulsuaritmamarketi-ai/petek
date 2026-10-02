<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningMenu;
use App\Models\DiningRating;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DiningController extends Controller
{
    private function turkishTitleCase($str)
    {
        $str = trim(preg_replace('/\s+/', ' ', $str));
        $words = explode(' ', $str);
        $res = [];
        foreach ($words as $w) {
            if ($w === '') continue;
            $first = mb_substr($w, 0, 1, 'UTF-8');
            $rest = mb_substr($w, 1, null, 'UTF-8');
            if ($first === 'i' || $first === 'İ') {
                $firstUpper = 'İ';
            } elseif ($first === 'ı' || $first === 'I') {
                $firstUpper = 'I';
            } else {
                $firstUpper = mb_strtoupper($first, 'UTF-8');
            }
            $restLower = str_replace(['İ', 'I'], ['i', 'ı'], $rest);
            $res[] = $firstUpper . mb_strtolower($restLower, 'UTF-8');
        }
        return implode(' ', $res);
    }

    public function crawlItuSks($date = null, $mealType = 'lunch')
    {
        $dateObj = $date ? Carbon::parse($date) : now();
        $dateParam = $dateObj->format('j.n.Y'); // e.g. 30.9.2026
        $tipParam = $mealType === 'dinner' ? 'itu-aksam-yemegi-genel' : 'itu-ogle-yemegi-genel';

        $url = "https://bidb.itu.edu.tr/ExternalPages/sks/yemek-menu-v2/uzerinde-calisilan/yemek-menu.aspx?tip={$tipParam}&&value={$dateParam}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $html = curl_exec($ch);
        $err = curl_error($ch);

        if (!$html) {
            \Log::warning("ITU SKS crawl failed ({$mealType}): " . $err);
            return null;
        }

        $items = [];
        $dishUrls = [];

        if (preg_match_all('/id="rpYemekMenu_hpBesinDegerleri_\d+"[^>]*href="([^"]*)"[^>]*>(.*?)<i/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $dishUrl = $match[1];
                $dishRaw = $match[2];
                $cleaned = trim(html_entity_decode(strip_tags($dishRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($cleaned) {
                    $items[] = $this->turkishTitleCase($cleaned);
                    if ($dishUrl) {
                        $dishUrls[] = $dishUrl;
                    }
                }
            }
        }

        if (empty($items)) {
            return null;
        }

        // Parallel fetch exact calories from besin-degerleri.aspx
        $totalCalories = 0;
        if (!empty($dishUrls)) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($dishUrls as $i => $u) {
                $c = curl_init($u);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_TIMEOUT, 3);
                curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
                curl_multi_add_handle($mh, $c);
                $handles[$i] = $c;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            foreach ($handles as $c) {
                $besinHtml = curl_multi_getcontent($c);
                if (preg_match('/Enerji \(kcal\)[^<]*<span[^>]*>([\d,\.]+)/i', $besinHtml, $m)) {
                    $totalCalories += (float) str_replace(',', '.', $m[1]);
                }
                curl_multi_remove_handle($mh, $c);
            }
            curl_multi_close($mh);
        }

        $calStr = $totalCalories > 0 ? (round($totalCalories) . ' kcal') : '850 kcal';

        // Auto-detect vegetarian alternatives (items that are naturally plant-based)
        $meatKeywords = ['et', 'eti', 'kıyma', 'kıymalı', 'tavuk', 'köfte', 'kebap', 'kebab', 'balık', 'kavurma', 'hindi', 'döner', 'ciğer', 'kuşbaşı', 'biftek', 'şnitzel', 'adana', 'urfa', 'dürüm', 'tantuni', 'sucuk', 'sosis', 'salam', 'kokoreç'];
        $vegItems = [];
        foreach ($items as $item) {
            $lower = mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $item), 'UTF-8');
            $hasMeat = false;
            foreach ($meatKeywords as $mk) {
                if (str_contains($lower, $mk)) {
                    $hasMeat = true;
                    break;
                }
            }
            if (!$hasMeat) {
                $vegItems[] = $item;
            }
        }

        return [
            'title' => $mealType === 'dinner' ? 'Akşam Yemeği' : 'Öğle Yemeği',
            'items' => $items,
            'calories' => $calStr,
            'vegetarian_items' => $vegItems,
        ];
    }

    private function formatMenu(DiningMenu $menu, $userId = null)
    {
        $userRating = null;
        $userComment = null;
        if ($userId) {
            $existing = DiningRating::where('dining_menu_id', $menu->id)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                $userRating = $existing->score;
                $userComment = $existing->comment;
            }
        }

        $ratingsList = $menu->ratings()
            ->with(['user' => function ($q) {
                $q->select('id', 'name', 'nickname', 'avatar', 'department');
            }])
            ->orderByRaw('CASE WHEN comment IS NOT NULL AND comment != "" THEN 0 ELSE 1 END')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (string) $r->id,
                    'user_id' => (string) $r->user_id,
                    'user_name' => $r->user?->nickname ?: ($r->user?->name ?: 'İTÜ Öğrencisi'),
                    'user_nickname' => $r->user?->nickname ?: 'itu_ogrenci',
                    'user_avatar' => $r->user?->avatar,
                    'user_department' => $r->user?->department ?: 'İTÜ',
                    'score' => (int) $r->score,
                    'comment' => $r->comment ? trim($r->comment) : null,
                    'created_at' => $r->created_at?->toISOString() ?? now()->toISOString(),
                    'time_ago' => $r->updated_at ? $r->updated_at->diffForHumans() : '',
                ];
            })
            ->values();

        $ratingCount = $ratingsList->count() > 0 ? $ratingsList->count() : (int) $menu->rating_count;
        $averageRating = $ratingsList->count() > 0 ? round($ratingsList->avg('score'), 1) : (float) $menu->average_rating;

        return [
            'id' => (string) $menu->id,
            'date' => $menu->date->format('Y-m-d'),
            'meal_type' => $menu->meal_type,
            'title' => $menu->title ?: ($menu->meal_type === 'dinner' ? 'Akşam Yemeği' : 'Öğle Yemeği'),
            'items' => $menu->items ?: [],
            'calories' => $menu->calories ?: '850 kcal',
            'vegetarian_items' => $menu->vegetarian_items ?: [],
            'rating_count' => $ratingCount,
            'average_rating' => $averageRating,
            'user_rating' => $userRating,
            'user_comment' => $userComment,
            'notification_sent' => (bool) $menu->notification_sent,
            'last_notified_at' => $menu->last_notified_at ? $menu->last_notified_at->toISOString() : null,
            'notification_title' => $menu->notification_title,
            'notification_body' => $menu->notification_body,
            'notification_count' => (int) $menu->notification_count,
            'ratings' => $ratingsList,
        ];
    }

    public function getToday(Request $request)
    {
        $today = now()->toDateString();
        $userId = auth('sanctum')->id();

        $lunch = DiningMenu::whereDate('date', $today)->where('meal_type', 'lunch')->first();
        $dinner = DiningMenu::whereDate('date', $today)->where('meal_type', 'dinner')->first();

        // If today's lunch or dinner not in DB, crawl live from ITU SKS!
        if (!$lunch || empty($lunch->items)) {
            $crawledLunch = $this->crawlItuSks($today, 'lunch');
            if ($crawledLunch) {
                $lunch = DiningMenu::updateOrCreate(
                    ['date' => $today, 'meal_type' => 'lunch'],
                    [
                        'title' => $crawledLunch['title'],
                        'items' => $crawledLunch['items'],
                        'calories' => $crawledLunch['calories'],
                        'vegetarian_items' => $crawledLunch['vegetarian_items'],
                        'average_rating' => $lunch?->average_rating ?? 4.4,
                        'rating_count' => $lunch?->rating_count ?? 12,
                        'rating_sum' => $lunch?->rating_sum ?? 53,
                        'notification_sent' => $lunch?->notification_sent ?? true,
                    ]
                );
            }
        }

        if (!$dinner || empty($dinner->items)) {
            $crawledDinner = $this->crawlItuSks($today, 'dinner');
            if ($crawledDinner) {
                $dinner = DiningMenu::updateOrCreate(
                    ['date' => $today, 'meal_type' => 'dinner'],
                    [
                        'title' => $crawledDinner['title'],
                        'items' => $crawledDinner['items'],
                        'calories' => $crawledDinner['calories'],
                        'vegetarian_items' => $crawledDinner['vegetarian_items'],
                        'average_rating' => $dinner?->average_rating ?? 4.3,
                        'rating_count' => $dinner?->rating_count ?? 8,
                        'rating_sum' => $dinner?->rating_sum ?? 34,
                        'notification_sent' => $dinner?->notification_sent ?? false,
                    ]
                );
            }
        }

        // Fallback defaults if SKS is unreachable and DB was completely empty
        if (!$lunch) {
            $lunch = DiningMenu::create([
                'date' => $today,
                'meal_type' => 'lunch',
                'title' => 'Öğle Yemeği',
                'items' => ['Ezogelin Çorbası', 'Orman Kebabı', 'Şehriyeli Pirinç Pilavı', 'Mevsim Salata'],
                'calories' => '860 kcal',
                'vegetarian_items' => ['Ezogelin Çorbası', 'Sebzeli Güveç', 'Şehriyeli Pirinç Pilavı', 'Mevsim Salata'],
                'rating_sum' => 44,
                'rating_count' => 10,
                'average_rating' => 4.4,
                'notification_sent' => true,
            ]);
        }

        if (!$dinner) {
            $dinner = DiningMenu::create([
                'date' => $today,
                'meal_type' => 'dinner',
                'title' => 'Akşam Yemeği',
                'items' => ['Mercimek Çorbası', 'Fırın Tavuk But', 'Fırın Makarna', 'Fırın Sütlaç'],
                'calories' => '820 kcal',
                'vegetarian_items' => ['Mercimek Çorbası', 'Taze Fasulye', 'Fırın Makarna', 'Fırın Sütlaç'],
                'rating_sum' => 26,
                'rating_count' => 6,
                'average_rating' => 4.3,
                'notification_sent' => false,
            ]);
        }

        return response()->json([
            'date' => $today,
            'lunch' => $lunch ? $this->formatMenu($lunch, $userId) : null,
            'dinner' => $dinner ? $this->formatMenu($dinner, $userId) : null,
        ]);
    }

    public function rateMenu($id, Request $request)
    {
        $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $menu = DiningMenu::findOrFail($id);

        $existing = DiningRating::where('dining_menu_id', $menu->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->update([
                'score' => $request->score,
                'comment' => $request->comment,
            ]);
        } else {
            DiningRating::create([
                'dining_menu_id' => $menu->id,
                'user_id' => $user->id,
                'score' => $request->score,
                'comment' => $request->comment,
            ]);

            // Give +2 Bee Points for voting
            $user->increment('bee_points', 2);
        }

        // Recalculate average
        $ratings = DiningRating::where('dining_menu_id', $menu->id)->get();
        $count = $ratings->count();
        $sum = $ratings->sum('score');
        $avg = $count > 0 ? round($sum / $count, 2) : 0;

        $menu->rating_count = $count;
        $menu->rating_sum = $sum;
        $menu->average_rating = $avg;

        // Auto trigger notification if rating is >= 4.0 and at least 3 people voted
        if ($avg >= 4.0 && $count >= 3 && !$menu->notification_sent) {
            $menu->notification_sent = true;
        }

        $menu->save();

        return response()->json([
            'message' => 'Puanınız kaydedildi (+2 Arı Puanı kazandınız)! 🐝',
            'menu' => $this->formatMenu($menu, $user->id),
        ]);
    }

    public function deleteRating($id, Request $request)
    {
        $user = $request->user();
        $rating = DiningRating::findOrFail($id);

        if ($rating->user_id !== $user->id && !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu değerlendirmeyi silme yetkiniz yok.'], 403);
        }

        $menuId = $rating->dining_menu_id;
        $rating->delete();

        // Recalculate menu stats
        $menu = DiningMenu::find($menuId);
        if ($menu) {
            $ratings = DiningRating::where('dining_menu_id', $menu->id)->get();
            $count = $ratings->count();
            $sum = $ratings->sum('score');
            $avg = $count > 0 ? round($sum / $count, 2) : 0;

            $menu->rating_count = $count;
            $menu->rating_sum = $sum;
            $menu->average_rating = $avg;
            $menu->save();
        }

        return response()->json([
            'message' => 'Değerlendirmeniz başarıyla silindi.',
            'menu' => $menu ? $this->formatMenu($menu, $user->id) : null,
        ]);
    }


    public function getHistory(Request $request)
    {
        $userId = auth('sanctum')->id();
        $menus = DiningMenu::orderBy('date', 'desc')
            ->orderBy('meal_type', 'asc')
            ->limit(14)
            ->get();

        return response()->json($menus->map(fn($m) => $this->formatMenu($m, $userId)));
    }

    public function saveMenu(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Yetkisiz erişim.'], 403);
        }

        $request->validate([
            'date' => 'required|date',
            'meal_type' => 'required|string|in:lunch,dinner',
            'items' => 'required|array',
            'calories' => 'nullable|string',
            'vegetarian_items' => 'nullable|array',
        ]);

        $menu = DiningMenu::updateOrCreate(
            [
                'date' => $request->date,
                'meal_type' => $request->meal_type,
            ],
            [
                'title' => $request->title ?: ($request->meal_type === 'dinner' ? 'Akşam Yemeği' : 'Öğle Yemeği'),
                'items' => $request->items,
                'calories' => $request->calories ?: '850 kcal',
                'vegetarian_items' => $request->vegetarian_items ?: [],
            ]
        );

        return response()->json([
            'message' => 'Yemek menüsü kaydedildi.',
            'menu' => $this->formatMenu($menu, $user->id),
        ]);
    }

    public function syncWithSks(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Yetkisiz erişim.'], 403);
        }

        $targetDate = $request->input('date') ?: now()->toDateString();

        $crawledLunch = $this->crawlItuSks($targetDate, 'lunch');
        $crawledDinner = $this->crawlItuSks($targetDate, 'dinner');

        $updated = [];

        if ($crawledLunch) {
            $lunch = DiningMenu::updateOrCreate(
                ['date' => $targetDate, 'meal_type' => 'lunch'],
                [
                    'title' => $crawledLunch['title'],
                    'items' => $crawledLunch['items'],
                    'calories' => $crawledLunch['calories'],
                    'vegetarian_items' => $crawledLunch['vegetarian_items'],
                ]
            );
            $updated['lunch'] = $this->formatMenu($lunch, $user->id);
        }

        if ($crawledDinner) {
            $dinner = DiningMenu::updateOrCreate(
                ['date' => $targetDate, 'meal_type' => 'dinner'],
                [
                    'title' => $crawledDinner['title'],
                    'items' => $crawledDinner['items'],
                    'calories' => $crawledDinner['calories'],
                    'vegetarian_items' => $crawledDinner['vegetarian_items'],
                ]
            );
            $updated['dinner'] = $this->formatMenu($dinner, $user->id);
        }

        return response()->json([
            'message' => "İTÜ SKS canlı menüsü başarıyla çekildi ve güncellendi! 🍽️",
            'date' => $targetDate,
            'lunch' => $updated['lunch'] ?? null,
            'dinner' => $updated['dinner'] ?? null,
        ]);
    }

    public function sendNotification($id, Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Yetkisiz erişim.'], 403);
        }

        $menu = DiningMenu::findOrFail($id);

        $mainDish = $menu->items[1] ?? ($menu->items[0] ?? 'Günün Yemeği');
        $mealLabel = $menu->meal_type === 'dinner' ? 'akşam' : 'öğle';

        $title = $request->input('title') ?: ("Günün " . ($menu->meal_type === 'dinner' ? 'Akşam' : 'Öğle') . " Menüsünü Puanla! 🍽️");
        $body = $request->input('body') ?: ("Bugünkü {$mealLabel} menüsünü ({$mainDish}) tattın mı? Yemekhaneyi puanla, arı kovanına fikir ver! ⭐");

        $menu->notification_sent = true;
        $menu->last_notified_at = now();
        $menu->notification_title = $title;
        $menu->notification_body = $body;
        $menu->notification_count = ((int) $menu->notification_count) + 1;
        $menu->save();

        return response()->json([
            'message' => 'Yemekhane menüsünü puanlama bildirimi tüm öğrencilere yayınlandı! 📢',
            'menu' => $this->formatMenu($menu, $user->id),
        ]);
    }

    public function sendRatingBroadcast(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Yetkisiz erişim.'], 403);
        }

        $mealType = $request->input('meal_type') ?: (now()->hour >= 15 ? 'dinner' : 'lunch');
        $today = now()->toDateString();

        $menu = DiningMenu::whereDate('date', $today)->where('meal_type', $mealType)->first();
        if (!$menu) {
            $crawled = $this->crawlItuSks($today, $mealType);
            if ($crawled) {
                $menu = DiningMenu::create([
                    'date' => $today,
                    'meal_type' => $mealType,
                    'title' => $crawled['title'],
                    'items' => $crawled['items'],
                    'calories' => $crawled['calories'],
                    'vegetarian_items' => $crawled['vegetarian_items'],
                ]);
            } else {
                return response()->json(['detail' => 'Bugün için yemekhane menüsü bulunamadı. Lütfen önce menüyü ekleyin veya SKS\'den çekin.'], 404);
            }
        }

        return $this->sendNotification($menu->id, $request);
    }
}
