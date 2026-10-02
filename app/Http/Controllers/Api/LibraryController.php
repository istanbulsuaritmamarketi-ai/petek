<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryFloor;
use App\Models\LibraryReport;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    private function ensureDefaultFloorsExist(): void
    {
        if (LibraryFloor::count() === 0) {
            $defaultFloors = [
                [
                    'name' => 'Kat 1 (Giriş & Grup Çalışma)',
                    'floor_code' => 'kat_1',
                    'building' => 'Mustafa İnan Kütüphanesi',
                    'capacity' => 260,
                    'occupancy_rate' => 0,
                    'socket_status' => 'plentiful',
                    'noise_level' => 'moderate',
                    'wifi_quality' => 'Eduroam Güçlü',
                    'report_count' => 0,
                    'last_reported_at' => null,
                ],
                [
                    'name' => 'Kat 2 (Bireysel & Prizli Masalar)',
                    'floor_code' => 'kat_2',
                    'building' => 'Mustafa İnan Kütüphanesi',
                    'capacity' => 320,
                    'occupancy_rate' => 0,
                    'socket_status' => 'limited',
                    'noise_level' => 'silent',
                    'wifi_quality' => 'Eduroam Güçlü',
                    'report_count' => 0,
                    'last_reported_at' => null,
                ],
                [
                    'name' => 'Kat 3 (Derin Sessiz Çalışma)',
                    'floor_code' => 'kat_3',
                    'building' => 'Mustafa İnan Kütüphanesi',
                    'capacity' => 210,
                    'occupancy_rate' => 0,
                    'socket_status' => 'plentiful',
                    'noise_level' => 'silent',
                    'wifi_quality' => 'Eduroam Güçlü',
                    'report_count' => 0,
                    'last_reported_at' => null,
                ],
                [
                    'name' => '7/24 Rezerv Salonu',
                    'floor_code' => 'rezerv',
                    'building' => 'Mustafa İnan Kütüphanesi',
                    'capacity' => 150,
                    'occupancy_rate' => 0,
                    'socket_status' => 'limited',
                    'noise_level' => 'silent',
                    'wifi_quality' => 'Eduroam Mükemmel',
                    'report_count' => 0,
                    'last_reported_at' => null,
                ],
                [
                    'name' => 'MED-A Açık Çalışma Alanı',
                    'floor_code' => 'med_a',
                    'building' => 'Merkezi Derslik Binası',
                    'capacity' => 180,
                    'occupancy_rate' => 0,
                    'socket_status' => 'plentiful',
                    'noise_level' => 'moderate',
                    'wifi_quality' => 'Eduroam Orta',
                    'report_count' => 0,
                    'last_reported_at' => null,
                ],
            ];

            foreach ($defaultFloors as $floorData) {
                LibraryFloor::create($floorData);
            }
        }
    }

    public function getRadar()
    {
        $this->ensureDefaultFloorsExist();

        $floors = LibraryFloor::all()->map(function (LibraryFloor $floor) {
            $availableSeats = max(0, (int) round($floor->capacity * (1 - ($floor->occupancy_rate / 100))));

            $timeAgoText = 'Henüz bildirim yapılmadı';
            if ($floor->last_reported_at) {
                $diffMinutes = (int) round($floor->last_reported_at->diffInMinutes(now()));
                if ($diffMinutes <= 0) {
                    $timeAgoText = 'Az önce';
                } elseif ($diffMinutes < 60) {
                    $timeAgoText = "{$diffMinutes} dk önce";
                } else {
                    $timeAgoText = $floor->last_reported_at->format('H:i');
                }
            }

            return [
                'id' => (string) $floor->id,
                'name' => $floor->name,
                'floor_code' => $floor->floor_code,
                'building' => $floor->building,
                'capacity' => $floor->capacity,
                'occupancy_rate' => $floor->occupancy_rate,
                'available_seats' => $availableSeats,
                'socket_status' => $floor->socket_status, // plentiful, limited, none
                'noise_level' => $floor->noise_level,     // silent, moderate, noisy
                'wifi_quality' => $floor->wifi_quality,
                'report_count' => $floor->report_count,
                'last_reported_at' => $floor->last_reported_at ? $floor->last_reported_at->toISOString() : null,
                'last_reported_human' => $timeAgoText,
            ];
        });

        return response()->json([
            'status' => 'success',
            'building' => 'Mustafa İnan Kütüphanesi & Çalışma Alanları',
            'floors' => $floors,
        ]);
    }

    public function submitReport(Request $request)
    {
        $validated = $request->validate([
            'floor_id' => 'required|exists:library_floors,id',
            'occupancy_level' => 'required|in:low,medium,high,full',
            'socket_level' => 'required|in:plentiful,limited,none',
            'noise_level' => 'required|in:silent,moderate,noisy',
            'comment' => 'nullable|string|max:150',
        ]);

        $user = $request->user();

        // 1. Save Report
        $report = LibraryReport::create([
            'floor_id' => $validated['floor_id'],
            'user_id' => $user->id,
            'occupancy_level' => $validated['occupancy_level'],
            'socket_level' => $validated['socket_level'],
            'noise_level' => $validated['noise_level'],
            'comment' => $validated['comment'] ?? null,
        ]);

        // 2. Recalculate Floor Live Stats
        $floor = LibraryFloor::findOrFail($validated['floor_id']);
        $recentReports = LibraryReport::where('floor_id', $floor->id)
            ->where('created_at', '>=', now()->subMinutes(60))
            ->get();

        $occMap = [
            'low' => 25,
            'medium' => 55,
            'high' => 80,
            'full' => 95,
        ];

        if ($recentReports->isNotEmpty()) {
            $avgOcc = $recentReports->avg(fn($r) => $occMap[$r->occupancy_level] ?? 50);
            $floor->occupancy_rate = (int) round($avgOcc);

            // Most frequent socket status
            $topSocket = $recentReports->groupBy('socket_level')->map->count()->sortDesc()->keys()->first();
            if ($topSocket) {
                $floor->socket_status = $topSocket;
            }

            // Most frequent noise level
            $topNoise = $recentReports->groupBy('noise_level')->map->count()->sortDesc()->keys()->first();
            if ($topNoise) {
                $floor->noise_level = $topNoise;
            }
        } else {
            $floor->occupancy_rate = $occMap[$validated['occupancy_level']] ?? 50;
            $floor->socket_status = $validated['socket_level'];
            $floor->noise_level = $validated['noise_level'];
        }

        $floor->report_count = $floor->report_count + 1;
        $floor->last_reported_at = now();
        $floor->save();

        // 3. Award +5 Bee Points
        $user->increment('bee_points', 5);

        return response()->json([
            'status' => 'success',
            'message' => 'Durum bildirimi kaydedildi! +5 Arı Puanı kazandın. 🐝',
            'earned_points' => 5,
            'current_bee_points' => $user->fresh()->bee_points,
            'floor' => [
                'id' => (string) $floor->id,
                'name' => $floor->name,
                'occupancy_rate' => $floor->occupancy_rate,
                'socket_status' => $floor->socket_status,
                'noise_level' => $floor->noise_level,
                'report_count' => $floor->report_count,
            ],
        ]);
    }

    public function adminFloors(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $this->ensureDefaultFloorsExist();

        $floors = LibraryFloor::all();
        return response()->json($floors);
    }

    public function storeFloor(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'building' => 'required|string|max:100',
            'floor_code' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1',
            'occupancy_rate' => 'nullable|integer|min:0|max:100',
            'socket_status' => 'required|in:plentiful,limited,none',
            'noise_level' => 'required|in:silent,moderate,noisy',
            'wifi_quality' => 'nullable|string|max:100',
        ]);

        $baseCode = !empty($validated['floor_code'])
            ? \Illuminate\Support\Str::slug($validated['floor_code'], '_')
            : \Illuminate\Support\Str::slug($validated['name'], '_');

        $floorCode = $baseCode ?: 'kat_' . time();
        $originalFloorCode = $floorCode;
        $counter = 1;
        while (LibraryFloor::where('floor_code', $floorCode)->exists()) {
            $floorCode = $originalFloorCode . '_' . $counter++;
        }

        $floor = LibraryFloor::create([
            'name' => $validated['name'],
            'floor_code' => $floorCode,
            'building' => $validated['building'],
            'capacity' => (int) $validated['capacity'],
            'occupancy_rate' => isset($validated['occupancy_rate']) ? (int) $validated['occupancy_rate'] : 20,
            'socket_status' => $validated['socket_status'],
            'noise_level' => $validated['noise_level'],
            'wifi_quality' => $validated['wifi_quality'] ?: 'Eduroam Güçlü',
            'report_count' => 0,
            'last_reported_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Yeni kat / çalışma salonu eklendi.',
            'floor' => $floor,
        ], 201);
    }

    public function updateFloor(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $floor = LibraryFloor::find($id);
        if (!$floor) {
            return response()->json(['detail' => 'Kat veya çalışma alanı bulunamadı.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'building' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'occupancy_rate' => 'nullable|integer|min:0|max:100',
            'socket_status' => 'required|in:plentiful,limited,none',
            'noise_level' => 'required|in:silent,moderate,noisy',
            'wifi_quality' => 'nullable|string|max:100',
        ]);

        $floor->update([
            'name' => $validated['name'],
            'building' => $validated['building'],
            'capacity' => (int) $validated['capacity'],
            'occupancy_rate' => isset($validated['occupancy_rate']) ? (int) $validated['occupancy_rate'] : $floor->occupancy_rate,
            'socket_status' => $validated['socket_status'],
            'noise_level' => $validated['noise_level'],
            'wifi_quality' => $validated['wifi_quality'] ?: $floor->wifi_quality,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kat / çalışma salonu başarıyla güncellendi.',
            'floor' => $floor,
        ]);
    }

    public function deleteFloor(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json(['detail' => 'Bu işlem için yetkiniz yok.'], 403);
        }

        $floor = LibraryFloor::find($id);
        if (!$floor) {
            return response()->json(['detail' => 'Kat veya çalışma alanı bulunamadı.'], 404);
        }

        LibraryReport::where('floor_id', $floor->id)->delete();
        $floor->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kat / çalışma salonu silindi.',
        ]);
    }
}

