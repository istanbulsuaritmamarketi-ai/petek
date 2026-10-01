<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETEK Admin Yönetim Paneli</title>
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

    <!-- TOPBAR -->
    <header class="border-b border-brand-border bg-brand-card/80 backdrop-blur px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/" class="flex items-center gap-2">
                <span class="text-2xl">🐝</span>
                <span class="text-lg font-bold text-white">PETEK <span class="text-brand-gold text-xs">YÖNETİM</span></span>
            </a>
        </div>
        <nav class="flex items-center gap-6 text-sm font-medium">
            <a href="/admin" class="text-brand-gold font-bold">Genel Bakış</a>
            <a href="/admin/professors" class="text-gray-300 hover:text-white transition">Hocalar</a>
            <a href="/admin/zones" class="text-gray-300 hover:text-white transition">Mekanlar</a>
            <a href="/admin/reports" class="text-gray-300 hover:text-white transition flex items-center gap-1.5">
                Raporlar
                @if($stats['reports_pending'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-bold">{{ $stats['reports_pending'] }}</span>
                @endif
            </a>
            <a href="/" class="text-xs text-brand-muted hover:text-white transition">Siteye Dön →</a>
        </nav>
    </header>

    <!-- CONTENT -->
    <main class="max-w-7xl w-full mx-auto px-6 py-8 flex-1">
        <h1 class="text-2xl font-bold text-white mb-6">Sistem Özeti & İstatistikler</h1>

        <!-- STAT CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-brand-card border border-brand-border rounded-2xl p-5">
                <span class="text-xs text-brand-muted font-medium block">Toplam Öğrenci</span>
                <span class="text-2xl font-extrabold text-white mt-1 block">{{ $stats['users_count'] }}</span>
            </div>
            <div class="bg-brand-card border border-brand-border rounded-2xl p-5">
                <span class="text-xs text-brand-muted font-medium block">Paylaşılan Gönderi</span>
                <span class="text-2xl font-extrabold text-brand-gold mt-1 block">{{ $stats['posts_count'] }}</span>
            </div>
            <div class="bg-brand-card border border-brand-border rounded-2xl p-5">
                <span class="text-xs text-brand-muted font-medium block">Kayıtlı Hoca</span>
                <span class="text-2xl font-extrabold text-white mt-1 block">{{ $stats['professors_count'] }}</span>
            </div>
            <div class="bg-brand-card border border-brand-border rounded-2xl p-5">
                <span class="text-xs text-brand-muted font-medium block">Aktif Mekan</span>
                <span class="text-2xl font-extrabold text-white mt-1 block">{{ $stats['zones_count'] }}</span>
            </div>
            <div class="bg-brand-card border border-brand-border rounded-2xl p-5">
                <span class="text-xs text-brand-muted font-medium block">Bekleyen Şikayet</span>
                <span class="text-2xl font-extrabold text-red-400 mt-1 block">{{ $stats['reports_pending'] }}</span>
            </div>
        </div>

        <!-- 2 COLUMNS: RECENT POSTS & RECENT REPORTS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Son Gönderiler -->
            <div class="bg-brand-card border border-brand-border rounded-2xl p-6">
                <h2 class="font-bold text-lg text-white mb-4 flex items-center justify-between">
                    <span>Son Paylaşılan Gönderiler</span>
                    <span class="text-xs text-brand-muted">Son 5 post</span>
                </h2>
                <div class="space-y-4">
                    @forelse($recentPosts as $post)
                        <div class="p-4 rounded-xl bg-brand-dark/50 border border-brand-border/60">
                            <div class="flex items-center justify-between text-xs text-brand-muted mb-2">
                                <span class="font-semibold text-white">{{ $post->is_anonymous ? 'Anonim (' . $post->user?->department . ')' : $post->user?->nickname }}</span>
                                <span>{{ $post->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-200">{{ $post->content }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-brand-muted py-4">Henüz gönderi yok.</p>
                    @endforelse
                </div>
            </div>

            <!-- Son Raporlar -->
            <div class="bg-brand-card border border-brand-border rounded-2xl p-6">
                <h2 class="font-bold text-lg text-white mb-4 flex items-center justify-between">
                    <span>İnceleme Bekleyen Raporlar</span>
                    <a href="/admin/reports" class="text-xs text-brand-gold hover:underline">Tümünü Gör →</a>
                </h2>
                <div class="space-y-4">
                    @forelse($recentReports as $report)
                        <div class="p-4 rounded-xl bg-brand-dark/50 border border-brand-border/60 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold uppercase px-2 py-0.5 rounded bg-red-500/20 text-red-400 mr-2">{{ $report->target_type }}</span>
                                <span class="text-sm text-white font-medium">{{ $report->reason }}</span>
                                <span class="text-xs text-brand-muted block mt-1">Raporlayan: {{ $report->reporter?->nickname ?? 'Bilinmeyen' }}</span>
                            </div>
                            <span class="text-xs px-2.5 py-1 rounded bg-yellow-500/10 text-yellow-400 font-semibold capitalize">{{ $report->status }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-brand-muted py-4">Bekleyen şikayet raporu yok. 🎉</p>
                    @endforelse
                </div>
            </div>
        </div>
    </main>

</body>
</html>
