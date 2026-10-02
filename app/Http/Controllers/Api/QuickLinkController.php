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

    private function processImage(?string $image): ?string
    {
        if (!$image) return null;
        if (str_starts_with($image, 'data:image')) {
            if (preg_match('/^data:image\/(\w+);base64,/', $image, $match)) {
                $ext = strtolower($match[1] === 'jpeg' ? 'jpg' : $match[1]);
                $contentData = base64_decode(substr($image, strpos($image, ',') + 1));
                $uploadDir = public_path('uploads/portals');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $filename = 'portal_' . uniqid() . '.' . $ext;
                file_put_contents($uploadDir . '/' . $filename, $contentData);
                $url = url('uploads/portals/' . $filename);
                if (str_contains(config('app.url'), 'https://') || request()->isSecure() || str_contains($url, 'itupetek.com.tr')) {
                    $url = str_replace('http://', 'https://', $url);
                }
                return $url;
            }
        }
        return $image;
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
            'image' => 'nullable|string',
            'color' => 'nullable|string|max:50',
            'badge' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        if (!empty($validated['image'])) {
            $validated['image'] = $this->processImage($validated['image']);
        }

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
            'image' => 'nullable|string',
            'color' => 'nullable|string|max:50',
            'badge' => 'nullable|string|max:50',
            'order' => 'nullable|integer',
        ]);

        if (array_key_exists('image', $validated)) {
            $validated['image'] = $this->processImage($validated['image']);
        }

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
