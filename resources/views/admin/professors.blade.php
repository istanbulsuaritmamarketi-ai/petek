<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETEK Admin — Hoca Yönetimi</title>
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
            <a href="/admin/professors" class="text-brand-gold font-bold">Hocalar</a>
            <a href="/admin/zones" class="text-gray-300 hover:text-white transition">Mekanlar</a>
            <a href="/admin/reports" class="text-gray-300 hover:text-white transition">Raporlar</a>
            <a href="/" class="text-xs text-brand-muted hover:text-white transition">Siteye Dön →</a>
        </nav>
    </header>

    <main class="max-w-7xl w-full mx-auto px-6 py-8 flex-1">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-white">Hocametre — Kayıtlı Akademisyenler</h1>
        </div>

        @if(session('success'))
            <div class="p-4 mb-6 rounded-xl bg-green-500/10 border border-green-500/30 text-green-400 text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- HOCA EKLEME FORMU -->
            <div class="bg-brand-card border border-brand-border rounded-2xl p-6 h-fit">
                <h2 class="font-bold text-lg text-white mb-4">Yeni Hoca Ekle</h2>
                <form action="/admin/professors" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs text-brand-muted block mb-1">Hoca Adı Soyadı</label>
                        <input type="text" name="name" required placeholder="Örn: Prof. Dr. Ahmet Yılmaz" class="w-full bg-brand-dark border border-brand-border rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-gold">
                    </div>
                    <div>
                        <label class="text-xs text-brand-muted block mb-1">Bölüm</label>
                        <input type="text" name="department" required placeholder="Örn: Bilgisayar Mühendisliği" class="w-full bg-brand-dark border border-brand-border rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-gold">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand-dark font-bold text-sm transition">
                        Kaydet
                    </button>
                </form>
            </div>

            <!-- HOCA LİSTESİ -->
            <div class="lg:col-span-2 bg-brand-card border border-brand-border rounded-2xl p-6">
                <h2 class="font-bold text-lg text-white mb-4">Kayıtlı Hocalar ({{ $professors->total() }})</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-brand-muted uppercase border-b border-brand-border/60">
                            <tr>
                                <th class="pb-3">Hoca</th>
                                <th class="pb-3">Bölüm</th>
                                <th class="pb-3 text-center">Değerlendirme</th>
                                <th class="pb-3 text-center">Ort. Puan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border/40">
                            @forelse($professors as $prof)
                                <tr>
                                    <td class="py-3 font-semibold text-white">{{ $prof->name }}</td>
                                    <td class="py-3 text-brand-muted text-xs">{{ $prof->department }}</td>
                                    <td class="py-3 text-center text-xs text-white">{{ $prof->ratings_count }} adet</td>
                                    <td class="py-3 text-center font-bold text-brand-gold text-xs">
                                        {{ $prof->avg_teaching }} / 5
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-6 text-brand-muted">Henüz kayıtlı hoca yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $professors->links() }}
                </div>
            </div>
        </div>
    </main>

</body>
</html>
