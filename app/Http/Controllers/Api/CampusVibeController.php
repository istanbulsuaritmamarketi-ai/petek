<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampusVibe;
use Illuminate\Http\Request;

class CampusVibeController extends Controller
{
    public function index()
    {
        // 24 saat süresi dolmuş veya 24 saatten eski tüm vibe'ları veritabanından kalıcı olarak sil
        CampusVibe::where('expires_at', '<=', now())
            ->orWhere('created_at', '<=', now()->subHours(24))
            ->delete();

        $vibes = CampusVibe::with(['user:id,nickname,avatar,department'])
            ->where('expires_at', '>', now())
            ->where('created_at', '>', now()->subHours(24))
            ->latest()
            ->limit(30)
            ->get();

        return response()->json($vibes);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Yeni paylaşım öncesi eski süresi dolmuş vibe'ları temizle
        CampusVibe::where('expires_at', '<=', now())
            ->orWhere('created_at', '<=', now()->subHours(24))
            ->delete();

        $validated = $request->validate([
            'emoji' => 'nullable|string|max:16',
            'content' => 'required|string|max:200',
            'location_name' => 'nullable|string|max:100',
        ]);

        // 🤖 Arı Muhafız: Cinsel / Müstehcen İçerik Denetimi
        $violation = \App\Services\ContentModerationService::inspectAndLog($validated['content'], null, $user, 'vibe');
        if ($violation && $violation['blocked']) {
            return response()->json([
                'detail' => $violation['message'],
                'reason' => $violation['reason'],
            ], 422);
        }

        $locationName = !empty($validated['location_name']) && trim($validated['location_name']) !== ''
            ? trim($validated['location_name'])
            : null;

        $vibe = CampusVibe::create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'] ?? '⚡',
            'content' => $validated['content'],
            'location_name' => $locationName,
            'expires_at' => now()->addHours(24),
        ]);

        // Reward 3 Bee Points for sharing campus vibe!
        $user->increment('bee_points', 3);

        $vibe->load('user:id,nickname,avatar,department');

        return response()->json([
            'message' => 'Vibe paylaşıldı! +3 Arı Puanı kazandın.',
            'vibe' => $vibe,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $query = CampusVibe::where('id', $id);
        // Adminler tüm vibe'ları silebilir, normal kullanıcılar yalnızca kendisininkini
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $vibe = $query->first();

        if (!$vibe) {
            return response()->json(['message' => 'Vibe bulunamadı veya yetkiniz yok.'], 404);
        }

        $vibe->delete();

        return response()->json(['message' => 'Vibe başarıyla silindi.']);
    }
}
