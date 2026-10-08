<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query();

        if ($request->filled('q')) {
            $search = trim($request->q);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('organizer_club', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter')) {
            $filter = $request->filter;
            if ($filter === 'club') {
                $query->where('is_club_event', true);
            } elseif ($filter === 'itu') {
                $query->where(function ($q) {
                    $q->where('is_club_event', false)->orWhereNull('is_club_event');
                });
            }
        }

        $events = $query->with('club')->orderBy('event_date', 'asc')->get();

        return response()->json($events->map(function ($e) {
            $club = $e->club;
            return [
                'id' => (string) $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'date' => $e->event_date ? $e->event_date->toISOString() : now()->toISOString(),
                'location' => $e->location ?: 'İTÜ Kampüsü',
                'organizer_club' => $club ? ($club->club_name ?: $club->name) : ($e->organizer_club ?: 'İTÜ Etkinlik'),
                'image' => $e->image,
                'link' => $e->link ?: 'https://etkinlik.itu.edu.tr/',
                'fee' => $e->fee ?: 'Ücretsiz',
                'is_club_event' => (bool) $e->is_club_event,
                'club_id' => $e->club_id ? (string) $e->club_id : null,
                'club_name' => $club ? ($club->club_name ?: $club->name) : null,
                'club_nickname' => $club?->nickname,
                'club_avatar' => $club?->avatar,
                'created_at' => $e->created_at ? $e->created_at->toISOString() : now()->toISOString(),
            ];
        }));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // RBAC: Club account, student who owns a club, club_manager, or admin can create events
        $isClubUser = ($user->account_type === 'club');
        $managedClubs = \App\Models\User::where('owner_id', $user->id)->where('account_type', 'club')->get();
        $isClubOwner = $managedClubs->isNotEmpty();
        $isAdmin = in_array((int) $user->id, [1, 3]) || $user->role === 'admin';

        if (!$isClubUser && !$isClubOwner && !$isAdmin && $user->role !== 'club_manager') {
            return response()->json(['detail' => 'Etkinlik oluşturma yetkiniz yok. Kulüp hesabı veya kulüp yöneticisi olmalısınız.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'date' => 'required|date',
            'location' => 'required|string|max:150',
            'organizer_club' => 'nullable|string|max:100',
            'image' => 'nullable|string',
            'link' => 'nullable|string|max:500',
            'fee' => 'nullable|string|max:50',
            'club_id' => 'nullable|integer',
        ]);

        $targetClubId = null;
        if ($isClubUser) {
            $targetClubId = $user->id;
            $organizer = $request->organizer_club ?: ($user->club_name ?: $user->name);
        } elseif ($request->filled('club_id')) {
            $selectedClub = $managedClubs->firstWhere('id', (int) $request->club_id);
            if ($selectedClub || $isAdmin) {
                $targetClubId = (int) $request->club_id;
                $targetClubUser = $selectedClub ?: \App\Models\User::find($targetClubId);
                $organizer = $request->organizer_club ?: ($targetClubUser?->club_name ?: $targetClubUser?->name ?: 'İTÜ Kulübü');
            } else {
                $organizer = $request->organizer_club ?: 'İTÜ Kulübü';
            }
        } elseif ($isClubOwner) {
            $firstClub = $managedClubs->first();
            $targetClubId = $firstClub->id;
            $organizer = $request->organizer_club ?: ($firstClub->club_name ?: $firstClub->name);
        } else {
            $organizer = $request->organizer_club ?: 'İTÜ Kulübü';
        }

        $event = Event::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'event_date' => $request->date,
            'location' => $request->location,
            'organizer_club' => $organizer,
            'image' => $request->image,
            'link' => $request->link ?: 'https://etkinlik.itu.edu.tr/',
            'fee' => $request->fee ?: 'Ücretsiz',
            'is_club_event' => true,
            'club_id' => $targetClubId,
        ]);

        return response()->json([
            'id' => (string) $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'date' => $event->event_date ? $event->event_date->toISOString() : now()->toISOString(),
            'location' => $event->location,
            'organizer_club' => $event->organizer_club,
            'image' => $event->image,
            'link' => $event->link,
            'fee' => $event->fee,
            'is_club_event' => (bool) $event->is_club_event,
            'club_id' => $event->club_id ? (string) $event->club_id : null,
            'created_at' => $event->created_at ? $event->created_at->toISOString() : now()->toISOString(),
        ], 201);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($event->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['detail' => 'Yetkiniz yok'], 403);
        }

        $event->delete();
        return response()->json(['message' => 'Etkinlik silindi']);
    }

    public function crawlItuEvents(Request $request = null)
    {
        if ($request && $request->user() && !in_array($request->user()->role, ['admin', 'club_manager'])) {
            return response()->json(['detail' => 'Yetkiniz yok'], 403);
        }

        $url = 'https://etkinlik.itu.edu.tr/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PetekITU/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200 || empty($html)) {
            return response()->json(['message' => 'İTÜ Etkinlikler sitesine erişilemedi.', 'count' => 0], 502);
        }

        $addedCount = 0;
        $adminUser = \App\Models\User::where('role', 'admin')->first() ?: \App\Models\User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        if (preg_match_all('/<li[^>]*class=["\'][^"\']*media[^"\']*["\'][^>]*>(.*?)<\/li>/is', $html, $matches)) {
            foreach ($matches[1] as $itemHtml) {
                // Title
                if (!preg_match('/<h4[^>]*class=["\']media-heading["\'][^>]*>(.*?)<\/h4>/is', $itemHtml, $tm)) {
                    continue;
                }
                $title = trim(strip_tags($tm[1]));
                if (!$title) continue;

                // Image
                $img = null;
                if (preg_match('/<img[^>]*src=["\']([^"\']+)["\']/is', $itemHtml, $im)) {
                    $img = $im[1];
                    if (!str_starts_with($img, 'http')) {
                        $img = 'https://etkinlik.itu.edu.tr' . $img;
                    }
                }

                // Date & Location
                $location = 'İTÜ Ayazağa Kampüsü';
                $eventDate = now()->addDays(2);
                if (preg_match('/<span>(.*?)<\/span>/is', $itemHtml, $sm)) {
                    $spanText = trim(strip_tags($sm[1]));
                    $parts = explode('|', $spanText);
                    $dateStr = trim($parts[0] ?? '');
                    if (isset($parts[1]) && trim($parts[1])) {
                        $location = trim($parts[1]);
                    }

                    if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $dateStr, $dm)) {
                        try {
                            $eventDate = \Carbon\Carbon::createFromFormat('d.m.Y', "{$dm[1]}.{$dm[2]}.{$dm[3]}")->setTime(10, 0);
                        } catch (\Exception $e) {
                            $eventDate = now()->addDays(3);
                        }
                    }
                }

                // Link
                $link = 'https://etkinlik.itu.edu.tr/';
                if (preg_match('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>/is', $itemHtml, $lm)) {
                    $rawHref = trim($lm[1]);
                    if ($rawHref) {
                        $link = str_starts_with($rawHref, 'http') ? $rawHref : ('https://etkinlik.itu.edu.tr/' . ltrim($rawHref, '/'));
                    }
                }

                // Fee / Ücret
                $fee = 'Ücretsiz';
                if (preg_match('/<strong>.*?<\/strong>\s*\|\s*([^<]+)/is', $itemHtml, $feem)) {
                    $fee = trim($feem[1]);
                }

                // Description (Detailed)
                $desc = $title;
                if (preg_match('/<p>(.*?)<\/p>/is', $itemHtml, $dm)) {
                    $desc = trim(strip_tags($dm[1]));
                }

                // Fetch full description from detail page if available
                if (str_contains($link, 'EtkinlikDetay.aspx')) {
                    $detailCh = curl_init($link);
                    curl_setopt($detailCh, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($detailCh, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($detailCh, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PetekITU/1.0');
                    curl_setopt($detailCh, CURLOPT_TIMEOUT, 4);
                    $detailHtml = curl_exec($detailCh);
                    curl_close($detailCh);
                    if ($detailHtml && preg_match('/<span[^>]*id=["\'][^"\']*lblAciklama[^"\']*["\'][^>]*>(.*?)<\/span>/is', $detailHtml, $aciklamaM)) {
                        $fullDesc = trim(html_entity_decode(strip_tags($aciklamaM[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if (!empty($fullDesc)) {
                            $desc = $fullDesc;
                        }
                    }
                }

                // Category / Club
                $organizer = 'İTÜ Etkinlik';
                if (preg_match('/<strong>(.*?)<\/strong>/is', $itemHtml, $cm)) {
                    $organizer = trim(strip_tags($cm[1]));
                }

                Event::updateOrCreate(
                    ['title' => $title],
                    [
                        'user_id' => $adminId,
                        'description' => $desc,
                        'event_date' => $eventDate,
                        'location' => $location,
                        'organizer_club' => $organizer ?: 'İTÜ',
                        'image' => $img,
                        'link' => $link,
                        'fee' => $fee,
                    ]
                );
                $addedCount++;
            }
        }

        return response()->json([
            'message' => "{$addedCount} etkinlik İTÜ Etkinlikler portalından başarıyla çekildi!",
            'count' => $addedCount,
            'events' => Event::orderBy('event_date', 'asc')->get(),
        ]);
    }
}
