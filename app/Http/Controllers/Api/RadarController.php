<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\ZoneVote;
use App\Models\User;
use Illuminate\Http\Request;

class RadarController extends Controller
{
    private function calculateZoneData(Zone $zone): array
    {
        $activeStudents = User::whereNotNull('location_updated_at')
            ->where('location_updated_at', '>=', now()->subMinutes(20))
            ->whereBetween('location_lat', [$zone->lat - 0.0015, $zone->lat + 0.0015])
            ->whereBetween('location_lng', [$zone->lng - 0.0015, $zone->lng + 0.0015])
            ->count();

        $capacity = max(20, (int) ($zone->capacity ?: 200));
        $occupancyPercent = min(100, max(5, (int) round(($activeStudents / $capacity) * 100)));

        $recentVotes = $zone->recentVotes()->get();
        if ($recentVotes->isNotEmpty()) {
            $density = $recentVotes->groupBy('density_level')->map->count()->sortDesc()->keys()->first();
        } else {
            if ($occupancyPercent > 70) $density = 'very_high';
            elseif ($occupancyPercent > 45) $density = 'high';
            elseif ($occupancyPercent > 20) $density = 'medium';
            else $density = 'low';
        }

        return [
            'density' => $density,
            'occupancy_rate' => $occupancyPercent,
            'active_students' => $activeStudents,
        ];
    }

    public function getZones(Request $request)
    {
        $search = $request->get('search');
        $campus = $request->get('campus');
        $type = $request->get('zone_type');
        $query = Zone::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('building', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($campus && $campus !== 'all' && $campus !== 'Tümü') {
            $query->where('campus', $campus);
        }

        if ($type && $type !== 'all' && $type !== 'Tümü') {
            $query->where('zone_type', $type);
        }

        $zones = $query->orderBy('name', 'asc')->get();

        return response()->json($zones->map(fn($zone) => $this->formatZone($zone))->values());
    }

    public function formatZone(Zone $zone): array
    {
        $data = $this->calculateZoneData($zone);

        return [
            'id' => (string) $zone->id,
            'name' => $zone->name,
            'campus' => $zone->campus ?: 'Ayazağa',
            'building' => $zone->building,
            'floor' => $zone->floor,
            'description' => $zone->description,
            'directions' => $zone->directions,
            'map_url' => $zone->map_url,
            'opening_hours' => $zone->opening_hours ?: '08:30 - 23:00',
            'is_active' => (bool) ($zone->is_active ?? true),
            'phone' => $zone->phone,
            'image' => $zone->image,
            'lat' => (float) $zone->lat,
            'lng' => (float) $zone->lng,
            'latitude' => (float) $zone->lat,
            'longitude' => (float) $zone->lng,
            'radius' => (int) $zone->radius,
            'capacity' => (int) ($zone->capacity ?: 100),
            'active_students' => $data['active_students'],
            'current_students' => $data['active_students'],
            'occupancy_rate' => $data['occupancy_rate'],
            'density' => $data['density'],
            'calculated_density' => $data['occupancy_rate'],
            'zone_type' => $zone->zone_type ?: 'building',
            'socket_status' => $zone->socket_status ?: 'Bol',
            'noise_level' => $zone->noise_level ?: 'Sessiz',
            'wifi_quality' => $zone->wifi_quality ?: 'Eduroam Güçlü',
            'amenities' => $zone->amenities ?: [],
            'has_menu' => (bool) $zone->has_menu,
            'price_list' => $zone->price_list ?? [],
            'vote_density' => $zone->vote_density ?? 'low',
            'last_updated' => $zone->updated_at ? $zone->updated_at->toISOString() : now()->toISOString(),
        ];
    }

    public function pingLocation(Request $request)
    {
        $user = auth('sanctum')->user();
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        if ($user && $lat && $lng) {
            $user->location_lat = $lat;
            $user->location_lng = $lng;
            $user->location_updated_at = now();
            $user->save();
        }

        // Find which zone user is in (if any)
        $currentZone = null;
        if ($lat && $lng) {
            $zones = Zone::all();
            foreach ($zones as $z) {
                $dist = sqrt(pow(($z->lat - $lat), 2) + pow(($z->lng - $lng), 2));
                if ($dist < 0.0015) {
                    $currentZone = $z->name;
                    break;
                }
            }
        }

        return response()->json([
            'message' => 'Konum başarıyla güncellendi',
            'current_zone' => $currentZone,
        ]);
    }

    public function getZone($id)
    {
        $zone = Zone::findOrFail($id);
        return response()->json($this->formatZone($zone));
    }

    public function vote(Request $request, $id)
    {
        $request->validate([
            'density_level' => 'required|in:low,medium,high,very_high',
        ]);

        $user = $request->user();
        $zone = Zone::findOrFail($id);

        ZoneVote::create([
            'zone_id' => $zone->id,
            'user_id' => $user->id,
            'density_level' => $request->density_level,
        ]);

        return response()->json(['message' => 'Oyunuz kaydedildi']);
    }

    public function suggestPrice(Request $request, $id)
    {
        $request->validate([
            'product_name' => 'required|string',
            'price' => 'required|numeric',
        ]);

        $zone = Zone::findOrFail($id);
        $prices = $zone->price_list ?? [];

        $prices[] = [
            'id' => uniqid(),
            'product_name' => $request->product_name,
            'price' => (float) $request->price,
            'submitted_by' => (string) $request->user()->id,
            'status' => 'approved',
            'created_at' => now()->toISOString(),
        ];

        $zone->price_list = $prices;
        $zone->save();

        return response()->json(['message' => 'Fiyat önerisi eklendi', 'price_list' => $prices]);
    }

    public function getSuggestions($id)
    {
        $zone = Zone::findOrFail($id);
        $suggestions = $zone->suggestions ?? [];
        return response()->json($suggestions);
    }

    public function addSuggestion(Request $request, $id)
    {
        $request->validate(['content' => 'required|string']);
        $zone = Zone::findOrFail($id);
        $suggestions = $zone->suggestions ?? [];
        $user = $request->user();

        $item = [
            'id' => uniqid(),
            'user_id' => (string) $user->id,
            'user_nickname' => $user->nickname,
            'content' => $request->content,
            'created_at' => now()->toISOString(),
        ];

        $suggestions[] = $item;
        $zone->suggestions = $suggestions;
        $zone->save();

        return response()->json($item, 201);
    }

    public function getMetroStatus()
    {
        return $this->getM2AyazagaLive();
    }

    public function getM2AyazagaLive(?Request $request = null)
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        $currentHour = (int) $now->format('G');
        $currentMinute = (int) $now->format('i');
        $dayOfWeek = (int) $now->format('N'); // 1 = Monday, 5 = Friday, 6 = Saturday, 7 = Sunday
        $isNightMetro = ($dayOfWeek == 5 || $dayOfWeek == 6); // Friday night or Saturday night

        $isClosed = ($currentHour >= 0 && $currentHour < 6 && !$isNightMetro);

        if ($isClosed) {
            return response()->json([
                'station' => 'İTÜ-Ayazağa',
                'status' => 'closed',
                'status_message' => 'Metro kapalıdır (İlk sefer saat 06:00)',
                'yenikapi' => [
                    'direction' => 'Yenikapı',
                    'next_minutes' => null,
                    'next_trains' => ['06:00', '06:07', '06:14'],
                    'headway' => '7 dk',
                    'status' => 'closed',
                ],
                'haciosman' => [
                    'direction' => 'Hacıosman',
                    'next_minutes' => null,
                    'next_trains' => ['06:02', '06:09', '06:16'],
                    'headway' => '7 dk',
                    'status' => 'closed',
                ],
                'yenikapiTime' => 'Kapalı (06:00)',
                'haciosmanTime' => 'Kapalı (06:00)',
                'lastUpdate' => now()->toISOString(),
            ]);
        }

        // Try live query to Metro Istanbul
        $liveData = $this->queryMetroIstanbulLive($now);
        if ($liveData) {
            return response()->json($liveData);
        }

        // Fallback schedule calculation based on headway
        return response()->json($this->calculateEstimatedSchedule($now));
    }

    private function queryMetroIstanbulLive(\DateTime $now): ?array
    {
        try {
            $ch1 = curl_init('https://www.metro.istanbul/SeferDurumlari/SeferDetaylari');
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch1, CURLOPT_HEADER, true);
            curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch1, CURLOPT_TIMEOUT, 6);
            $resp = curl_exec($ch1);
            $headerSize = curl_getinfo($ch1, CURLINFO_HEADER_SIZE);
            $header = substr($resp, 0, $headerSize);
            $body = substr($resp, $headerSize);

            preg_match_all('/set-cookie:\s*([^;]+)/i', $header, $cookieMatches);
            $cookieHeader = implode('; ', $cookieMatches[1] ?? []);

            preg_match("/formData\.append\(['\"]kod['\"],\s*['\"]([^'\"]+)['\"]\)/", $body, $km);
            $kod = $km[1] ?? '';

            if (!$kod) return null;

            $hourStr = $now->format('H');
            $minStr = $now->format('i');
            $dateStr = $now->format('d.m.Y');

            // Route 35: Hacıosman -> Yenikapı
            $trainsYenikapi = $this->fetchMetroTrains($cookieHeader, $kod, '35', $hourStr, $minStr, $dateStr);
            // Route 34: Yenikapı -> Hacıosman
            $trainsHaciosman = $this->fetchMetroTrains($cookieHeader, $kod, '34', $hourStr, $minStr, $dateStr);

            if (empty($trainsYenikapi) && empty($trainsHaciosman)) {
                return null;
            }

            $currentTotalMins = ((int)$hourStr) * 60 + ((int)$minStr);

            $calcNext = function(array $trains) use ($currentTotalMins) {
                $upcoming = [];
                $nextMins = 5;
                foreach ($trains as $t) {
                    $parts = explode(':', $t);
                    if (count($parts) === 2) {
                        $m = ((int)$parts[0]) * 60 + ((int)$parts[1]);
                        $diff = $m - $currentTotalMins;
                        if ($diff >= 0 && count($upcoming) < 5) {
                            $upcoming[] = $t;
                        }
                    }
                }
                if (!empty($upcoming)) {
                    $firstParts = explode(':', $upcoming[0]);
                    $firstM = ((int)$firstParts[0]) * 60 + ((int)$firstParts[1]);
                    $nextMins = max(1, $firstM - $currentTotalMins);
                }
                return [$nextMins, $upcoming];
            };

            [$ykMins, $ykTrains] = $calcNext($trainsYenikapi);
            [$hoMins, $hoTrains] = $calcNext($trainsHaciosman);

            return [
                'station' => 'İTÜ-Ayazağa',
                'status' => 'active',
                'status_message' => 'Canlı Metro İstanbul Sefer Tarifesi',
                'yenikapi' => [
                    'direction' => 'Yenikapı',
                    'next_minutes' => $ykMins,
                    'next_trains' => !empty($ykTrains) ? $ykTrains : array_slice($trainsYenikapi, 0, 4),
                    'headway' => '5-7 dk',
                    'status' => 'active',
                ],
                'haciosman' => [
                    'direction' => 'Hacıosman',
                    'next_minutes' => $hoMins,
                    'next_trains' => !empty($hoTrains) ? $hoTrains : array_slice($trainsHaciosman, 0, 4),
                    'headway' => '5-7 dk',
                    'status' => 'active',
                ],
                'yenikapiTime' => "{$ykMins} dk",
                'haciosmanTime' => "{$hoMins} dk",
                'source' => 'metro_istanbul_live',
                'lastUpdate' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchMetroTrains(string $cookieHeader, string $kod, string $route, string $hour, string $min, string $date): array
    {
        $ch = curl_init('https://www.metro.istanbul/SeferDurumlari/AJAXSeferGetir');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'secim' => '2',
            'saat' => $hour,
            'dakika' => $min,
            'tarih1' => $date,
            'tarih2' => '',
            'station' => '32',
            'route' => $route,
            'kod' => $kod,
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . $cookieHeader,
            'X-Requested-With: XMLHttpRequest',
            'Referer: https://www.metro.istanbul/SeferDurumlari/SeferDetaylari',
            'Origin: https://www.metro.istanbul',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        $json = json_decode($res, true);

        $trains = [];
        if (isset($json['sefer']) && is_array($json['sefer'])) {
            foreach ($json['sefer'] as $s) {
                if (isset($s['zaman'])) {
                    $trains[] = $s['zaman'];
                }
            }
        }
        return $trains;
    }

    private function calculateEstimatedSchedule(\DateTime $now): array
    {
        $hour = (int) $now->format('G');
        $min = (int) $now->format('i');

        // Headway calculation for M2
        $headway = 6;
        if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
            $headway = 4; // Peak hours
        } elseif ($hour >= 22) {
            $headway = 10; // Late night
        }

        $minUntilNextYk = $headway - ($min % $headway);
        $minUntilNextHo = ($headway - ($min % $headway) + 2) % $headway ?: $headway;

        $genUpcoming = function(int $initialOffset, int $hw) use ($now) {
            $times = [];
            for ($i = 0; $i < 4; $i++) {
                $clone = clone $now;
                $clone->modify('+' . ($initialOffset + ($i * $hw)) . ' minutes');
                $times[] = $clone->format('H:i');
            }
            return $times;
        };

        return [
            'station' => 'İTÜ-Ayazağa',
            'status' => 'active',
            'status_message' => 'Tarifeli Sefer Düzeni (M2)',
            'yenikapi' => [
                'direction' => 'Yenikapı',
                'next_minutes' => $minUntilNextYk,
                'next_trains' => $genUpcoming($minUntilNextYk, $headway),
                'headway' => "{$headway} dk",
                'status' => 'active',
            ],
            'haciosman' => [
                'direction' => 'Hacıosman',
                'next_minutes' => $minUntilNextHo,
                'next_trains' => $genUpcoming($minUntilNextHo, $headway),
                'headway' => "{$headway} dk",
                'status' => 'active',
            ],
            'yenikapiTime' => "{$minUntilNextYk} dk",
            'haciosmanTime' => "{$minUntilNextHo} dk",
            'source' => 'm2_schedule_estimator',
            'lastUpdate' => now()->toISOString(),
        ];
    }


    public function storeZone(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'zone_type' => 'required|string',
        ]);

        $lat = $request->input('lat') ?? $request->input('latitude') ?? 41.1054;
        $lng = $request->input('lng') ?? $request->input('longitude') ?? 29.0235;
        $radius = (int) ($request->input('radius') ?? 100);

        $zone = Zone::create([
            'name' => $request->input('name'),
            'campus' => $request->input('campus', 'Ayazağa'),
            'building' => $request->input('building'),
            'floor' => $request->input('floor'),
            'description' => $request->input('description'),
            'directions' => $request->input('directions'),
            'map_url' => $request->input('map_url'),
            'opening_hours' => $request->input('opening_hours', '08:30 - 23:00'),
            'is_active' => (bool) $request->input('is_active', true),
            'phone' => $request->input('phone'),
            'image' => $request->input('image'),
            'zone_type' => $request->input('zone_type', 'building'),
            'capacity' => (int) $request->input('capacity', 100),
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'socket_status' => $request->input('socket_status', 'Bol'),
            'noise_level' => $request->input('noise_level', 'Sessiz'),
            'wifi_quality' => $request->input('wifi_quality', 'Eduroam Güçlü'),
            'amenities' => $request->input('amenities', []),
            'has_menu' => (bool) $request->input('has_menu', false),
            'price_list' => [],
            'suggestions' => [],
            'vote_density' => 'low',
        ]);

        return response()->json($this->formatZone($zone), 201);
    }

    public function updateZone(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $zone = Zone::findOrFail($id);

        if ($request->has('name')) $zone->name = $request->input('name');
        if ($request->has('campus')) $zone->campus = $request->input('campus');
        if ($request->has('building')) $zone->building = $request->input('building');
        if ($request->has('floor')) $zone->floor = $request->input('floor');
        if ($request->has('description')) $zone->description = $request->input('description');
        if ($request->has('directions')) $zone->directions = $request->input('directions');
        if ($request->has('map_url')) $zone->map_url = $request->input('map_url');
        if ($request->has('opening_hours')) $zone->opening_hours = $request->input('opening_hours');
        if ($request->has('is_active')) $zone->is_active = (bool) $request->input('is_active');
        if ($request->has('phone')) $zone->phone = $request->input('phone');
        if ($request->has('image')) $zone->image = $request->input('image');
        if ($request->has('zone_type')) $zone->zone_type = $request->input('zone_type');
        if ($request->has('capacity')) $zone->capacity = (int) $request->input('capacity');
        if ($request->has('lat')) $zone->lat = (float) $request->input('lat');
        if ($request->has('latitude')) $zone->lat = (float) $request->input('latitude');
        if ($request->has('lng')) $zone->lng = (float) $request->input('lng');
        if ($request->has('longitude')) $zone->lng = (float) $request->input('longitude');
        if ($request->has('radius')) $zone->radius = (int) $request->input('radius');
        if ($request->has('socket_status')) $zone->socket_status = $request->input('socket_status');
        if ($request->has('noise_level')) $zone->noise_level = $request->input('noise_level');
        if ($request->has('wifi_quality')) $zone->wifi_quality = $request->input('wifi_quality');
        if ($request->has('amenities')) $zone->amenities = $request->input('amenities');
        if ($request->has('has_menu')) $zone->has_menu = (bool) $request->input('has_menu');

        $zone->save();

        return response()->json($this->formatZone($zone));
    }

    public function deleteZone(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $zone = Zone::findOrFail($id);
        $zone->delete();

        return response()->json(['message' => 'Mekan başarıyla silindi.']);
    }
}
