<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $query = Deal::query();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('provider', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $deals = $query->orderBy('created_at', 'desc')->get();

        return response()->json($deals);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'category_label' => 'nullable|string|max:100',
            'discount' => 'required|string|max:100',
            'description' => 'required|string',
            'promo_code' => 'nullable|string|max:100',
            'url' => 'required|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'verified' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $deal = Deal::create($validated);

        return response()->json($deal, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $deal = Deal::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'provider' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:50',
            'category_label' => 'nullable|string|max:100',
            'discount' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string',
            'promo_code' => 'nullable|string|max:100',
            'url' => 'sometimes|required|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'verified' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $deal->update($validated);

        return response()->json($deal);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $deal = Deal::findOrFail($id);
        $deal->delete();

        return response()->json(['message' => 'Fırsat başarıyla silindi.']);
    }

    public function crawlDeals(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $dealsList = [
            [
                'name' => 'Spotify Premium Öğrenci',
                'provider' => 'Spotify',
                'category' => 'entertainment',
                'category_label' => 'Müzik & Medya',
                'discount' => '%50 İndirim',
                'description' => 'Ayda sadece 32,99 TL. Üniversite e-posta (@itu.edu.tr) veya SheerID ile onaylı öğrenci aboneliği.',
                'promo_code' => 'SHEERID-ITU',
                'url' => 'https://www.spotify.com/tr/student/',
                'icon' => 'musical-notes',
                'color' => '#1DB954',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Apple Music & Apple TV+',
                'provider' => 'Apple',
                'category' => 'entertainment',
                'category_label' => 'Müzik & Dizi',
                'discount' => '%50 İndirim',
                'description' => 'Öğrenci fiyatı ayda 19,99 TL. Üstelik Apple TV+ aboneliği hediye!',
                'promo_code' => 'UNIDAYS-APPLE',
                'url' => 'https://www.apple.com/tr/apple-music/',
                'icon' => 'logo-apple',
                'color' => '#FC3C44',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'GitHub Student Developer Pack',
                'provider' => 'GitHub Education',
                'category' => 'software',
                'category_label' => 'Yazılım & Bulut',
                'discount' => '1000$+ Ücretsiz Araç',
                'description' => 'GitHub Pro, Copilot, DigitalOcean 200$, Namecheap domain, Canva Pro ve 80+ geliştirici aracı bedava!',
                'promo_code' => 'ITU-EDU-PACK',
                'url' => 'https://education.github.com/pack',
                'icon' => 'logo-github',
                'color' => '#2b3137',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Notion Education Plus',
                'provider' => 'Notion Labs',
                'category' => 'software',
                'category_label' => 'Üretkenlik & Not',
                'discount' => 'Ücretsiz Plus Plan',
                'description' => 'Sınırsız blok, sınırsız dosya yükleme ve yapay zeka entegrasyonu @itu.edu.tr ile tamamen ücretsiz.',
                'promo_code' => 'ITU-NOTION-EDU',
                'url' => 'https://www.notion.so/product/notion-for-education',
                'icon' => 'document-text',
                'color' => '#000000',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'JetBrains All Products Pack',
                'provider' => 'JetBrains',
                'category' => 'software',
                'category_label' => 'IDE & Geliştirme',
                'discount' => 'Tamamen Ücretsiz (300$+)',
                'description' => 'IntelliJ IDEA Ultimate, PyCharm Pro, WebStorm, CLion, Rider ve tüm JetBrains IDE araçları ücretsiz lisans.',
                'promo_code' => 'JETBRAINS-ISIC',
                'url' => 'https://www.jetbrains.com/community/education/#students',
                'icon' => 'code-slash',
                'color' => '#000000',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Amazon Prime Öğrenci',
                'provider' => 'Amazon Türkiye',
                'category' => 'tech',
                'category_label' => 'Alışveriş & Prime',
                'discount' => '%50 İndirim + Kargo Bedava',
                'description' => 'Prime Video, Prime Gaming ve ertesi gün teslimat avantajı öğrenci tarifesiyle ayda 19,50 TL.',
                'promo_code' => 'PRIME-OGRENCI',
                'url' => 'https://www.amazon.com.tr/prime',
                'icon' => 'cart',
                'color' => '#FF9900',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Figma Professional for Students',
                'provider' => 'Figma',
                'category' => 'software',
                'category_label' => 'Tasarım & UI/UX',
                'discount' => 'Ücretsiz Professional Plan',
                'description' => 'Sınırsız proje, ekip kütüphaneleri ve FigJam işbirlikleri öğrencilere 2 yıl boyunca ücretsiz.',
                'promo_code' => 'FIGMA-EDU',
                'url' => 'https://www.figma.com/education/',
                'icon' => 'color-palette',
                'color' => '#F24E1E',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Canva Pro Öğrenci & Eğitim',
                'provider' => 'Canva',
                'category' => 'software',
                'category_label' => 'Grafik Tasarım',
                'discount' => 'Ücretsiz Pro Abonelik',
                'description' => 'Milyonlarca şablon, arka plan silici ve premium tasarım araçları @itu.edu.tr ile ücretsiz.',
                'promo_code' => 'CANVA-EDU-ITU',
                'url' => 'https://www.canva.com/education/',
                'icon' => 'brush',
                'color' => '#00C4CC',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'YouTube Premium Öğrenci',
                'provider' => 'YouTube',
                'category' => 'entertainment',
                'category_label' => 'Video & Reklamsız',
                'discount' => '%45 İndirim',
                'description' => 'Reklamsız video, arka planda oynatma, indirme ve YouTube Music dahil aylık indirimli öğrenci tarifesi.',
                'promo_code' => 'YOUTUBE-STUDENT',
                'url' => 'https://www.youtube.com/premium/student',
                'icon' => 'logo-youtube',
                'color' => '#FF0000',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'Yemeksepeti Kampüs İndirimi',
                'provider' => 'Yemeksepeti',
                'category' => 'food',
                'category_label' => 'Yemek & Kafe',
                'discount' => '80 TL İndirim',
                'description' => 'İTÜ Ayazağa teslimatlarında seçili restoranlarda 200 TL üzeri siparişlerde 80 TL anında indirim!',
                'promo_code' => 'ITU80',
                'url' => 'https://www.yemeksepeti.com',
                'icon' => 'restaurant',
                'color' => '#EA004B',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'GetirYemek Öğrenci Menüleri',
                'provider' => 'Getir',
                'category' => 'food',
                'category_label' => 'Yemek & Fast Food',
                'discount' => '%35 İndirim',
                'description' => 'Kampüs çevresi restoranlarda ve Maslak bölgesinde geçerli özel avantajlı öğrenci fiyatları.',
                'promo_code' => 'KAMPUS35',
                'url' => 'https://getir.com/yemek/',
                'icon' => 'fast-food',
                'color' => '#5D3EBC',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
            [
                'name' => 'İstanbulkart Öğrenci Abonman',
                'provider' => 'İBB / Belbim',
                'category' => 'culture',
                'category_label' => 'Ulaşım & Şehir',
                'discount' => 'Aylık 250 TL (200 Geçiş)',
                'description' => 'Tüm metro, otobüs, metrobüs ve vapurlarda geçerli 200 basımlık avantajlı öğrenci aylık abonmanı.',
                'promo_code' => 'BELBIM-ITU',
                'url' => 'https://www.istanbulkart.istanbul/',
                'icon' => 'subway',
                'color' => '#0085CA',
                'verified' => true,
                'expires_at' => '2026-12-31',
            ],
        ];

        $updatedCount = 0;
        foreach ($dealsList as $dealData) {
            Deal::updateOrCreate(
                ['name' => $dealData['name']],
                $dealData
            );
            $updatedCount++;
        }

        return response()->json([
            'message' => "{$updatedCount} adet öğrenci fırsatı başarıyla tarandı ve güncellendi!",
            'count' => $updatedCount,
            'deals' => Deal::orderBy('created_at', 'desc')->get(),
        ]);
    }
}
