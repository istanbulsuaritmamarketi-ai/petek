<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\QuickLink;

class ComprehensiveVenuesAndLinksSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed or Update Quick Links with official ITU Map & Directory
        $quickLinks = [
            [
                'name' => 'İTÜ Canlı Kampüs & Ring Haritası',
                'category' => 'kampus',
                'category_label' => 'Yönlendirme & Ulaşım',
                'description' => 'Resmi İTÜ interaktif harita portalı, kampüs içi ring otobüsü canlı konum takibi, binalar ve yaya navigasyonu.',
                'url' => 'http://harita.itu.edu.tr',
                'icon' => 'map',
                'color' => '#3B82F6',
                'badge' => 'Canlı Ring',
                'order' => 1,
            ],
            [
                'name' => 'İTÜ Rehber Servisi',
                'category' => 'hizmet',
                'category_label' => 'Birimler & Fihrist',
                'description' => 'Tüm fakülteler, idari daireler, akademisyen oda numaraları ve resmi dahili telefon fihristi.',
                'url' => 'https://rehber.itu.edu.tr',
                'icon' => 'compass',
                'color' => '#10B981',
                'badge' => 'Resmi Fihrist',
                'order' => 2,
            ],
            [
                'name' => 'İTÜ Ring Sefer & Ulaşım Saatleri',
                'category' => 'kampus',
                'category_label' => 'Ulaşım',
                'description' => 'Ayazağa, Vadi, Metro ve kampüsler arası güncel mekik/ring sefer çizelgesi.',
                'url' => 'https://sks.itu.edu.tr/hizmetler/ulasim-hizmetleri',
                'icon' => 'bus',
                'color' => '#14B8A6',
                'badge' => 'SKS Seferleri',
                'order' => 3,
            ],
            [
                'name' => 'İTÜ Yerleşkeleri & Kampüs Hayatı',
                'category' => 'kampus',
                'category_label' => 'Yerleşkeler',
                'description' => 'Ayazağa, Taşkışla, Gümüşsuyu, Maçka ve Tuzla yerleşkeleri plan ve hizmetleri.',
                'url' => 'https://www.itu.edu.tr/kampus-hayati/kampusler',
                'icon' => 'business',
                'color' => '#8B5CF6',
                'badge' => '5 Kampüs',
                'order' => 4,
            ],
        ];

        foreach ($quickLinks as $linkData) {
            QuickLink::updateOrCreate(
                ['url' => $linkData['url']],
                $linkData
            );
        }

        // 2. Comprehensive ITU Venues across 5 Campuses
        $venues = [
            // AYAZAĞA KAMPÜSÜ
            [
                'name' => 'Mustafa İnan Merkez Kütüphanesi',
                'campus' => 'Ayazağa',
                'building' => 'Mustafa İnan Kütüphane Binası',
                'floor' => 'Zemin, 1. Kat ve 2. Kat',
                'description' => 'İTÜ\'nün ana akademik araştırma merkezi. 7/24 açık salonlar, sessiz çalışma alanları, grup odaları ve zengin kitap arşivi.',
                'directions' => 'Ayazağa metro çıkışından rektörlük yönüne 200m yürüyün, Merkezi Derslik Binası\'nın hemen yanında.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Mustafa-Inan-Kutuphanesi',
                'opening_hours' => '7/24 Açık',
                'is_active' => true,
                'phone' => '0212 285 30 12',
                'zone_type' => 'library',
                'capacity' => 1200,
                'socket_status' => 'Bol',
                'noise_level' => 'Sessiz',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.1054230,
                'lng' => 29.0235410,
                'radius' => 120,
                'amenities' => ['Priz', 'Wi-Fi', 'Klimalı', 'Engelli Erişimi', 'Sessiz Salon', 'Grup Odaları', 'Kitap Tarama'],
            ],
            [
                'name' => 'Merkezi Derslik Binası (MED-A & MED-B)',
                'campus' => 'Ayazağa',
                'building' => 'MED Kompleksi',
                'floor' => 'Zemin ve Giriş Kat',
                'description' => 'Geniş çalışma alanları, kantinler, amfiler ve grup projeleri için popüler buluşma noktası.',
                'directions' => 'Kütüphanenin hemen karşısı, ring durağının yanı.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Merkezi-Derslik-Binasi',
                'opening_hours' => '07:30 - 23:00',
                'is_active' => true,
                'phone' => '0212 285 60 00',
                'zone_type' => 'study',
                'capacity' => 600,
                'socket_status' => 'Bol',
                'noise_level' => 'Normal',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.1061100,
                'lng' => 29.0241000,
                'radius' => 100,
                'amenities' => ['Priz', 'Wi-Fi', 'Kantin', 'Geniş Masalar', 'Klimalı', 'Otomatlar'],
            ],
            [
                'name' => '75. Yıl Öğrenci Sosyal Merkezi & Yemekhane',
                'campus' => 'Ayazağa',
                'building' => '75. Yıl Binası',
                'floor' => 'Giriş ve 1. Kat',
                'description' => 'Ana yemekhane salonu, öğrenci kulüpleri odaları, kırtasiye, bankamatikler ve kafeteryalar.',
                'directions' => 'Gölet yolunda, Simit Sarayı ve bankamatiklerin bulunduğu ana meydan.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=75-Yil-Ogrenci-Merkezi',
                'opening_hours' => '08:00 - 20:30',
                'is_active' => true,
                'phone' => '0212 285 39 40',
                'zone_type' => 'canteen',
                'capacity' => 2500,
                'socket_status' => 'Orta',
                'noise_level' => 'Canlı / Sesli',
                'wifi_quality' => 'Eduroam Orta',
                'lat' => 41.1039800,
                'lng' => 29.0221000,
                'radius' => 150,
                'amenities' => ['Tabldot Yemek', 'Wi-Fi', 'Açık Teras', 'Kulüp Odaları', 'ATM', 'Kırtasiye'],
            ],
            [
                'name' => 'Süleyman Demirel Kültür Merkezi (SDKM)',
                'campus' => 'Ayazağa',
                'building' => 'SDKM',
                'floor' => 'Giriş Kat & Tiyatro Salonu',
                'description' => 'Kongreler, sempozyumlar, kariyer zirveleri, tiyatro ve konser etkinliklerinin merkezi.',
                'directions' => 'Ayazağa ana nizamiye girişinden sonra sol tarafta.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=SDKM',
                'opening_hours' => '08:30 - 22:00',
                'is_active' => true,
                'phone' => '0212 285 32 30',
                'zone_type' => 'social',
                'capacity' => 800,
                'socket_status' => 'Orta',
                'noise_level' => 'Normal',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.1085000,
                'lng' => 29.0210000,
                'radius' => 100,
                'amenities' => ['Konferans Salonu', 'Tiyatro', 'Wi-Fi', 'Kafeterya', 'Engelli Erişimi'],
            ],
            [
                'name' => 'İTÜ Stadyumu & Spor Salonu',
                'campus' => 'Ayazağa',
                'building' => 'Beden Eğitimi Bölümü Tesisleri',
                'floor' => 'Saha & Tribün',
                'description' => 'Açık atletizm pisti, futbol sahası, fitness salonu ve kapalı spor kompleksi.',
                'directions' => 'Vadi yurtlarına inen yokuşun sağ tarafında.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Stadyum',
                'opening_hours' => '08:00 - 22:00',
                'is_active' => true,
                'phone' => '0212 285 30 70',
                'zone_type' => 'gym',
                'capacity' => 5000,
                'socket_status' => 'Az',
                'noise_level' => 'Canlı / Sesli',
                'wifi_quality' => 'Eduroam Orta',
                'lat' => 41.1072000,
                'lng' => 29.0265000,
                'radius' => 200,
                'amenities' => ['Fitness', 'Koşu Pisti', 'Soyunma Odası', 'Açık Alan', 'Tribün'],
            ],

            // TAŞKIŞLA KAMPÜSÜ
            [
                'name' => 'Taşkışla Tarihi Orta Avlu & Kütüphane',
                'campus' => 'Taşkışla',
                'building' => 'Mimarlık Fakültesi Tarihi Binası',
                'floor' => 'Zemin Kat Avlu & 1. Kat',
                'description' => 'Tarihi mimari avlu, tasarım atölyeleri, sergiler ve Mimarlık Kütüphanesi.',
                'directions' => 'Taksim Meydanı veya Maçka Teleferik çıkışından 5 dakika yürüme mesafesinde.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Taskisla-Binasi',
                'opening_hours' => '08:00 - 23:00',
                'is_active' => true,
                'phone' => '0212 293 13 00',
                'zone_type' => 'library',
                'capacity' => 400,
                'socket_status' => 'Bol',
                'noise_level' => 'Sessiz',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.0416000,
                'lng' => 28.9892000,
                'radius' => 100,
                'amenities' => ['Tarihi Avlu', 'Priz', 'Wi-Fi', 'Atölyeler', 'Kantin', 'Mimarhane'],
            ],

            // GÜMÜŞSUYU KAMPÜSÜ
            [
                'name' => 'Gümüşsuyu Makine Fakültesi & Öğrenci Kantini',
                'campus' => 'Gümüşsuyu',
                'building' => 'Makine Fakültesi Tarihi Kışlası',
                'floor' => 'Giriş Kat Kantin & Çalışma Salonu',
                'description' => 'Makine ve İmalat mühendisliklerinin tarihi merkezi, kapalı spor salonu ve deniz manzaralı dinlenme alanları.',
                'directions' => 'Taksim Gümüşsuyu Caddesi üzerinden İnönü Stadyumu istikametinde sağda.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Gumussuyu-Binasi',
                'opening_hours' => '08:00 - 21:00',
                'is_active' => true,
                'phone' => '0212 293 13 00',
                'zone_type' => 'canteen',
                'capacity' => 350,
                'socket_status' => 'Orta',
                'noise_level' => 'Normal',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.0375000,
                'lng' => 28.9912000,
                'radius' => 100,
                'amenities' => ['Priz', 'Wi-Fi', 'Yemekhane', 'Spor Salonu', 'Tarihi Kışla'],
            ],

            // MAÇKA KAMPÜSÜ
            [
                'name' => 'Maçka İşletme Fakültesi & Amfitiyatro',
                'campus' => 'Maçka',
                'building' => 'Yabancı Diller & İşletme Fakültesi',
                'floor' => 'Zemin Kat & Bahçe',
                'description' => 'İşletme Fakültesi, hazırlık sınıfları (YDY), geniş çim alanlar ve tarihi Maçka Karakolu Binası.',
                'directions' => 'Beşiktaş - Nişantaşı aksında, Maçka Parkı\'nın karşısında.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Macka-Binasi',
                'opening_hours' => '08:00 - 22:00',
                'is_active' => true,
                'phone' => '0212 293 13 00',
                'zone_type' => 'faculty',
                'capacity' => 500,
                'socket_status' => 'Bol',
                'noise_level' => 'Normal',
                'wifi_quality' => 'Eduroam Güçlü',
                'lat' => 41.0450000,
                'lng' => 28.9950000,
                'radius' => 100,
                'amenities' => ['Priz', 'Wi-Fi', 'Çim Bahçe', 'Hazırlık Binası', 'Kantin'],
            ],

            // TUZLA KAMPÜSÜ
            [
                'name' => 'Tuzla Denizcilik Fakültesi & Eğitim Havuzu',
                'campus' => 'Tuzla',
                'building' => 'Denizcilik Fakültesi Yerleşkesi',
                'floor' => 'Kıyı Tesisleri & Kütüphane',
                'description' => 'Denizcilik simülatörleri, eğitim havuzu, kütüphane ve sahil eğitim iskelesi.',
                'directions' => 'Tuzla sahil yolu üzerinde Deniz Harp Okulu yakınında.',
                'map_url' => 'http://harita.itu.edu.tr/?bina=Tuzla-Yerleskesi',
                'opening_hours' => '08:30 - 18:00',
                'is_active' => true,
                'phone' => '0216 395 10 64',
                'zone_type' => 'faculty',
                'capacity' => 300,
                'socket_status' => 'Orta',
                'noise_level' => 'Sessiz',
                'wifi_quality' => 'Eduroam Orta',
                'lat' => 40.8170000,
                'lng' => 29.2960000,
                'radius' => 150,
                'amenities' => ['Sahil', 'Priz', 'Wi-Fi', 'Simülatör', 'Yemekhane'],
            ],
        ];

        foreach ($venues as $venue) {
            Zone::updateOrCreate(
                ['name' => $venue['name']],
                $venue
            );
        }
    }
}
