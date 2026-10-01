<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETEK Admin — Moderasyon ve Raporlar</title>
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
            <a href="/admin/zones" class="text-gray-300 hover:text-white transition">Mekanlar</a>
            <a href="/admin/reports" class="text-brand-gold font-bold">Raporlar</a>
            <a href="/" class="text-xs text-brand-muted hover:text-white transition">Siteye Dön →</a>
        </nav>
    </header>

    <main class="max-w-7xl w-full mx-auto px-6 py-8 flex-1">
        <h1 class="text-2xl font-bold text-white mb-6">Şikayet ve Moderasyon Raporları</h1>

        @if(session('success'))
            <div class="p-4 mb-6 rounded-xl bg-green-500/10 border border-green-500/30 text-green-400 text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-brand-card border border-brand-border rounded-2xl p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-brand-muted uppercase border-b border-brand-border/60">
                        <tr>
                            <th class="pb-3">Tür</th>
                            <th class="pb-3">Neden / Açıklama</th>
                            <th class="pb-3">Raporlayan</th>
                            <th class="pb-3">Tarih</th>
                            <th class="pb-3">Durum</th>
                            <th class="pb-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/40">
                        @forelse($reports as $report)
                            <tr>
                                <td class="py-3 font-bold uppercase text-xs">
                                    <span class="px-2 py-0.5 rounded bg-red-500/20 text-red-400">{{ $report->target_type }}</span>
                                </td>
                                <td class="py-3 text-white font-medium">{{ $report->reason }}</td>
                                <td class="py-3 text-xs text-brand-muted">{{ $report->reporter?->nickname ?? 'Bilinmeyen' }}</td>
                                <td class="py-3 text-xs text-brand-muted">{{ $report->created_at->diffForHumans() }}</td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $report->status === 'resolved' ? 'bg-green-500/20 text-green-400' : ($report->status === 'rejected' ? 'bg-gray-500/20 text-gray-400' : 'bg-yellow-500/20 text-yellow-400') }}">
                                        {{ $report->status }}
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    @if($report->status === 'pending')
                                        <div class="flex items-center justify-end gap-2">
                                            <form action="/admin/reports/{{ $report->id }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="px-3 py-1 rounded-lg bg-green-500/20 text-green-400 text-xs font-semibold hover:bg-green-500/30 transition">
                                                    Çözüldü
                                                </button>
                                            </form>
                                            <form action="/admin/reports/{{ $report->id }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="px-3 py-1 rounded-lg bg-red-500/20 text-red-400 text-xs font-semibold hover:bg-red-500/30 transition">
                                                    Reddet
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-6 text-brand-muted">Henüz şikayet raporu yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>
        </div>
    </main>

</body>
</html>
