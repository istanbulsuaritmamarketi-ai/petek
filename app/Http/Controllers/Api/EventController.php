<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('event_date', 'asc')->get();

        return response()->json($events->map(function ($e) {
            return [
                'id' => (string) $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'date' => $e->event_date->toISOString(),
                'location' => $e->location,
                'organizer_club' => $e->organizer_club,
                'image' => $e->image,
                'created_at' => $e->created_at->toISOString(),
            ];
        }));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // RBAC: Only club_manager and admin can create events
        if (!in_array($user->role, ['club_manager', 'admin'])) {
            return response()->json(['detail' => 'Etkinlik oluşturma yetkiniz yok'], 403);
        }

        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'date' => 'required|date',
            'location' => 'required|string',
            'organizer_club' => 'required|string',
            'image' => 'nullable|string',
        ]);

        $event = Event::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'event_date' => $request->date,
            'location' => $request->location,
            'organizer_club' => $request->organizer_club,
            'image' => $request->image,
        ]);

        return response()->json($event, 201);
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

                // Description
                $desc = $title;
                if (preg_match('/<p>(.*?)<\/p>/is', $itemHtml, $dm)) {
                    $desc = trim(strip_tags($dm[1]));
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
