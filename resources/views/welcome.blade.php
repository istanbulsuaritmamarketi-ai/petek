<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETEK / KOVAN — İTÜ Kampüs Super-App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
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
<body class="bg-brand-dark text-gray-100 min-h-screen flex flex-col selection:bg-brand-gold selection:text-black">

    <!-- NAVIGATION -->
    <header class="border-b border-brand-border bg-brand-dark/90 sticky top-0 z-50 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-brand-gold flex items-center justify-center text-brand-dark font-extrabold text-xl shadow-lg shadow-brand-gold/20">
                    🐝
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-white">PETEK</span>
                    <span class="text-xs text-brand-gold block font-semibold tracking-wider -mt-1">KOVAN V3</span>
                </div>
            </div>

            <nav class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="#radar" class="text-gray-300 hover:text-brand-gold transition">Kampüs Radarı</a>
                <a href="#hocametre" class="text-gray-300 hover:text-brand-gold transition">Hocametre</a>
                <a href="#mobil" class="text-gray-300 hover:text-brand-gold transition">Mobil Uygulama</a>
                <a href="#api" class="text-gray-300 hover:text-brand-gold transition">API Durumu</a>
            </nav>

            <div class="flex items-center space-x-3">
                <a href="/admin" class="text-xs font-semibold px-4 py-2 rounded-lg bg-brand-card hover:bg-brand-cardHover border border-brand-border text-white transition flex items-center gap-1.5">
                    <span>👑</span> Admin Paneli
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative overflow-hidden py-16 lg:py-24 border-b border-brand-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-brand-gold/10 border border-brand-gold/30 text-brand-gold text-xs font-semibold uppercase tracking-wider mb-6">
                <span>🚀</span> İTÜ Kampüs Dijital İkizi & Super-App
            </div>
            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
                İTÜ’nün En Hızlı, En Akıllı <br><span class="text-brand-gold">Öğrenci Ekosistemi</span>
            </h1>
            <p class="mt-6 text-lg text-brand-muted max-w-2xl mx-auto leading-relaxed">
                Kütüphane doluluk oranları, yemekhane fiyatları, tarafsız hoca değerlendirmeleri, fakülte savaşları ve kampüs sosyal akışı tek bir çatı altında.
            </p>

            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a href="#mobil" class="px-6 py-3.5 rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand-dark font-bold text-sm transition shadow-lg shadow-brand-gold/25 flex items-center gap-2">
                    <span>📱</span> Mobil Uygulamayı Aç
                </a>
                <a href="#radar" class="px-6 py-3.5 rounded-xl bg-brand-card hover:bg-brand-cardHover border border-brand-border text-white font-semibold text-sm transition flex items-center gap-2">
                    <span>📡</span> Kampüs Radarı
                </a>
            </div>
        </div>
    </section>

    <!-- KAMPÜS RADARI (CANLI MEKANLAR) -->
    <section id="radar" class="py-16 border-b border-brand-border" x-data="{
        zones: [],
        loading: true,
        async fetchZones() {
            try {
                const res = await fetch('/api/zones');
                this.zones = await res.json();
            } catch(e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        }
    }" x-init="fetchZones()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
                        <span>📡</span> Kampüs Canlı Yoğunluk Radarı
                    </h2>
                    <p class="text-sm text-brand-muted mt-1">Öğrenci oyları ve GPS sinyalleriyle anlık doluluk oranları</p>
                </div>
                <div class="mt-4 md:mt-0 flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-green-500/10 text-green-400 border border-green-500/20">🟢 Boş / Sakin</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">🟡 Orta</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-red-500/10 text-red-400 border border-red-500/20">🔴 Çok Yoğun</span>
                </div>
            </div>

            <!-- Kartlar -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <template x-if="loading">
                    <div class="col-span-3 text-center py-12 text-brand-muted">Mekan verileri yükleniyor...</div>
                </template>
                <template x-for="zone in zones" :key="zone.id">
                    <div class="bg-brand-card border border-brand-border rounded-2xl p-6 hover:border-brand-gold/40 transition">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-lg text-white" x-text="zone.name"></h3>
                                <span class="text-xs text-brand-muted capitalize" x-text="zone.zone_type"></span>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                                  :class="{
                                      'bg-green-500/10 text-green-400 border border-green-500/20': zone.calculated_density === 'low',
                                      'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20': zone.calculated_density === 'medium',
                                      'bg-orange-500/10 text-orange-400 border border-orange-500/20': zone.calculated_density === 'high',
                                      'bg-red-500/10 text-red-400 border border-red-500/20': zone.calculated_density === 'very_high'
                                  }"
                                  x-text="zone.calculated_density === 'low' ? 'Sakin' : (zone.calculated_density === 'medium' ? 'Orta' : (zone.calculated_density === 'high' ? 'Kalabalık' : 'Tıklım Tıklım'))">
                            </span>
                        </div>

                        <!-- Menü/Fiyat Listesi varsa -->
                        <template x-if="zone.has_menu && zone.price_list && zone.price_list.length > 0">
                            <div class="mt-4 pt-4 border-t border-brand-border/60">
                                <span class="text-xs font-semibold text-brand-gold block mb-2">🍽️ Popüler Fiyatlar:</span>
                                <div class="space-y-1 text-xs text-brand-muted">
                                    <template x-for="item in zone.price_list.slice(0, 3)" :key="item.id">
                                        <div class="flex justify-between">
                                            <span x-text="item.product_name"></span>
                                            <span class="font-bold text-white" x-text="item.price + ' ₺'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <!-- HOCAMETRE BÖLÜMÜ -->
    <section id="hocametre" class="py-16 border-b border-brand-border" x-data="{
        profs: [],
        loading: true,
        async fetchProfs() {
            try {
                const res = await fetch('/api/professors');
                this.profs = await res.json();
            } catch(e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        }
    }" x-init="fetchProfs()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
                    <span>🎓</span> Hocametre — İTÜ Hoca Puanlama
                </h2>
                <p class="text-sm text-brand-muted mt-1">Öğrencilerin yoklama, zorluk ve anlatım kriterlerine göre bağımsız hoca puanları</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-if="loading">
                    <div class="col-span-3 text-center py-12 text-brand-muted">Hoca listesi yükleniyor...</div>
                </template>
                <template x-for="prof in profs" :key="prof.id">
                    <div class="bg-brand-card border border-brand-border rounded-2xl p-6 hover:border-brand-gold/40 transition">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-brand-gold/10 border border-brand-gold/20 flex items-center justify-center text-lg font-bold text-brand-gold">
                                👨‍🏫
                            </div>
                            <div>
                                <h3 class="font-bold text-white text-base" x-text="prof.name"></h3>
                                <p class="text-xs text-brand-muted" x-text="prof.department"></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center pt-3 border-t border-brand-border/60">
                            <div class="bg-brand-dark/50 rounded-xl p-2.5">
                                <span class="text-[10px] text-brand-muted block">Yoklama</span>
                                <span class="text-sm font-bold text-brand-gold" x-text="prof.avg_attendance + ' / 5'"></span>
                            </div>
                            <div class="bg-brand-dark/50 rounded-xl p-2.5">
                                <span class="text-[10px] text-brand-muted block">Zorluk</span>
                                <span class="text-sm font-bold text-red-400" x-text="prof.avg_difficulty + ' / 5'"></span>
                            </div>
                            <div class="bg-brand-dark/50 rounded-xl p-2.5">
                                <span class="text-[10px] text-brand-muted block">Anlatım</span>
                                <span class="text-sm font-bold text-green-400" x-text="prof.avg_teaching + ' / 5'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <!-- MOBİL UYGULAMA VE DURUM -->
    <section id="mobil" class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-brand-card to-[#1E2128] border border-brand-border rounded-3xl p-8 lg:p-12 flex flex-col md:flex-row items-center justify-between gap-8">
                <div class="max-w-xl">
                    <span class="text-xs font-bold text-brand-gold uppercase tracking-wider">React Native + Expo SDK 54</span>
                    <h2 class="text-3xl font-extrabold text-white mt-2">Mobil Uygulama ile Kampüs Cebinde</h2>
                    <p class="text-brand-muted text-sm mt-3 leading-relaxed">
                        Arı Puanı topla, Pomodoro ile fakülteni zirveye taşı, anonim itiraf duvarında sesini duyur ve kampüs radarıyla kütüphanede boş masa ara.
                    </p>
                    <div class="mt-6 flex items-center gap-4 text-xs font-semibold text-gray-300">
                        <div class="flex items-center gap-1.5"><span class="text-green-400">✓</span> Laravel 12 API</div>
                        <div class="flex items-center gap-1.5"><span class="text-green-400">✓</span> MySQL Veritabanı</div>
                        <div class="flex items-center gap-1.5"><span class="text-green-400">✓</span> 0ms Yerel Test</div>
                    </div>
                </div>
                <div class="bg-brand-dark border border-brand-border rounded-2xl p-6 text-center">
                    <div class="w-36 h-36 bg-white rounded-xl mx-auto flex items-center justify-center text-brand-dark font-mono text-xs">
                        [ QR KOD ]
                    </div>
                    <span class="text-xs text-brand-muted block mt-3 font-medium">Expo Go ile Tara</span>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-brand-border py-8 bg-brand-dark mt-auto text-center text-xs text-brand-muted">
        <p>© 2026 PETEK / KOVAN — İstanbul Teknik Üniversitesi Kampüs Super-App</p>
        <p class="mt-1 text-gray-500">Cenuta & Laragon Yüksek Performanslı Altyapı</p>
    </footer>

</body>
</html>
