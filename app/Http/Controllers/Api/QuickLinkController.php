<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuickLink;
use Illuminate\Http\Request;

class QuickLinkController extends Controller
{
    public function index(Request $request)
    {
        // Eski kart.itu.edu.tr URL'sini otomatik olarak resmi güncel https://kampuskart.itu.edu.tr/User/Login bağlantısına çevir
        QuickLink::where('url', 'like', '%kart.itu.edu.tr%')
            ->update(['url' => 'https://kampuskart.itu.edu.tr/User/Login']);

        // 404 veren veya kapanan eski servisleri (itunotkutusu, sks 404 alt sayfaları, oidb vb.) otomatik temizle
        QuickLink::where('url', 'like', '%itunotkutusu.com%')
            ->orWhere('url', 'like', '%oidb.itu.edu.tr%')
            ->orWhere('url', 'like', '%sks.itu.edu.tr/yemekhane-hizmetleri%')
            ->orWhere('url', 'like', '%sks.itu.edu.tr/hizmetler/ulasim-hizmetleri%')
            ->delete();

        // Eski kariyer linkini resmi İKM portalına çevir
        QuickLink::where('url', 'like', '%kariyer.itu.edu.tr%')
            ->update(['url' => 'https://ikm.itu.edu.tr']);

        $query = QuickLink::query();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $links = $query->orderBy('order', 'asc')->orderBy('name', 'asc')->get();

        return response()->json($links);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'category_label' => 'nullable|string|max:100',
            'description' => 'required|string',
            'url' => 'required|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'badge' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $link = QuickLink::create($validated);

        return response()->json($link, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $link = QuickLink::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:50',
            'category_label' => 'nullable|string|max:100',
            'description' => 'sometimes|required|string',
            'url' => 'sometimes|required|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'badge' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        $link->update($validated);

        return response()->json($link);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $link = QuickLink::findOrFail($id);
        $link->delete();

        return response()->json(['message' => 'Hızlı bağlantı başarıyla silindi.']);
    }
}
