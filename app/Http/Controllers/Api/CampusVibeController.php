<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampusVibe;
use Illuminate\Http\Request;

class CampusVibeController extends Controller
{
    public function index()
    {
        $vibes = CampusVibe::with(['user:id,nickname,avatar,department'])
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(30)
            ->get();

        return response()->json($vibes);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'emoji' => 'nullable|string|max:16',
            'content' => 'required|string|max:200',
            'location_name' => 'nullable|string|max:100',
        ]);

        $vibe = CampusVibe::create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'] ?? '⚡',
            'content' => $validated['content'],
            'location_name' => $validated['location_name'] ?? null,
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
        $vibe = CampusVibe::where('id', $id)->where('user_id', $user->id)->first();

        if (!$vibe) {
            return response()->json(['message' => 'Vibe bulunamadı veya yetkiniz yok.'], 404);
        }

        $vibe->delete();

        return response()->json(['message' => 'Vibe silindi.']);
    }
}
