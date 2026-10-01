<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETEK Admin — Kampüs Mekanları</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            gold: '#FDB913',
                            dark: '#121316',
                            card: '#1B1D22',
                            cardHover: '#23262C',
                            border: '#2A2D35',
                            muted: '#9CA3AF'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-dark text-gray-100 min-h-screen flex flex-col">

    <header class="border-b border-brand-border bg-brand-card/80 backdrop-blur px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/admin" class="flex items-center gap-2">
                <span class="text-2xl">🐝</span>
                <span class="text-lg font-bold text-white">PETEK <span class="text-brand-gold text-xs">YÖNETİM</span></span>
            </a>
        </div>
        <nav class="flex items-center gap-6 text-sm font-medium">
            <a href="/admin" class="text-gray-300 hover:text-white transition">Genel Bakış</a>
            <a href="/admin/professors" class="text-gray-300 hover:text-white transition">Hocalar</a>
            <a href="/admin/zones" class="text-brand-gold font-bold">Mekanlar</a>
            <a href="/admin/reports" class="text-gray-300 hover:text-white transition">Raporlar</a>
            <a href="/" class="text-xs text-brand-muted hover:text-white transition">Siteye Dön →</a>
        </nav>
    </header>

    <main class="max-w-7xl w-full mx-auto px-6 py-8 flex-1">
        <h1 class="text-2xl font-bold text-white mb-6">Kampüs Radar Mekanları & Menüleri</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($zones as $zone)
                <div class="bg-brand-card border border-brand-border rounded-2xl p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-bold text-lg text-white">{{ $zone->name }}</h3>
                            <span class="text-xs text-brand-muted uppercase font-semibold">{{ $zone->zone_type }}</span>
                        </div>
                        <span class="text-xs text-brand-gold font-bold">{{ $zone->votes_count }} Oy</span>
                    </div>

                    <div class="mt-4 pt-4 border-t border-brand-border/60 text-xs text-brand-muted space-y-1">
                        <div>Koordinat: {{ $zone->lat }}, {{ $zone->lng }}</div>
                        <div>Yarıçap: {{ $zone->radius }} metre</div>
                        <div>Menü: {{ $zone->has_menu ? 'Var' : 'Yok' }}</div>
                    </div>

                    @if($zone->has_menu && !empty($zone->price_list))
                        <div class="mt-4 pt-3 border-t border-brand-border/40">
                            <span class="text-xs font-bold text-white block mb-2">Fiyat Listesi:</span>
                            <div class="space-y-1.5 text-xs">
                                @foreach($zone->price_list as $item)
                                    <div class="flex justify-between text-brand-muted">
                                        <span>{{ $item['product_name'] ?? '' }}</span>
                                        <span class="font-semibold text-white">{{ $item['price'] ?? 0 }} ₺</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-brand-muted">Henüz mekan tanımlanmamış.</div>
            @endforelse
        </div>
    </main>

</body>
</html>
