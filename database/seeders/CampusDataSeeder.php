<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Zone;
use App\Models\Professor;
use App\Models\Post;
use Illuminate\Support\Facades\Hash;

class CampusDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Demo Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@itu.edu.tr'],
            [
                'name' => 'Admin Arı',
                'nickname' => 'admin_ari',
                'password' => Hash::make('admin123'),
                'department' => 'Bilgisayar Mühendisliği',
                'faculty' => 'Bilgisayar ve Bilişim Fakültesi',
                'role' => 'admin',
                'account_type' => 'personal',
                'badges' => ['aktif_arı', 'kaşif'],
                'bee_points' => 150,
            ]
        );

        $student = User::firstOrCreate(
            ['email' => 'ogrenci@itu.edu.tr'],
            [
                'name' => 'İTÜ Öğrenci',
                'nickname' => 'ari_ogrenci',
                'password' => Hash::make('123456'),
                'department' => 'Endüstri Mühendisliği',
                'faculty' => 'İşletme Fakültesi',
                'role' => 'student',
                'account_type' => 'personal',
                'badges' => ['yeni_arı'],
                'bee_points' => 25,
            ]
        );

        // 2. Campus Zones (Radars)
        $zones = [
            [
                'name' => 'Mustafa İnan Kütüphanesi',
                'lat' => 41.1054231,
                'lng' => 29.0235412,
                'radius' => 120,
                'zone_type' => 'library',
                'has_menu' => false,
                'price_list' => [],
            ],
            [
                'name' => '75. Yıl Yemekhanesi',
                'lat' => 41.1060124,
                'lng' => 29.0250123,
                'radius' => 100,
                'zone_type' => 'canteen',
                'has_menu' => true,
                'price_list' => [
                    ['id' => '1', 'product_name' => 'Öğrenci Tabldot Menü', 'price' => 20, 'status' => 'approved'],
                    ['id' => '2', 'product_name' => 'Çay', 'price' => 5, 'status' => 'approved'],
                    ['id' => '3', 'product_name' => 'Su (500ml)', 'price' => 5, 'status' => 'approved'],
                    ['id' => '4', 'product_name' => 'Kahve', 'price' => 25, 'status' => 'approved'],
                ],
            ],
            [
                'name' => 'Merkezi Derslik Binası (MED-A)',
                'lat' => 41.1045123,
                'lng' => 29.0220341,
                'radius' => 90,
                'zone_type' => 'canteen',
                'has_menu' => true,
                'price_list' => [
                    ['id' => '1', 'product_name' => 'Tost', 'price' => 45, 'status' => 'approved'],
                    ['id' => '2', 'product_name' => 'Filtre Kahve', 'price' => 35, 'status' => 'approved'],
                ],
            ],
            [
                'name' => 'İTÜ Stadyumu & Spor Salonu',
                'lat' => 41.1080123,
                'lng' => 29.0210341,
                'radius' => 150,
                'zone_type' => 'gym',
                'has_menu' => false,
                'price_list' => [],
            ],
            [
                'name' => 'Arı Teknokent 3',
                'lat' => 41.1020123,
                'lng' => 29.0270341,
                'radius' => 150,
                'zone_type' => 'techpark',
                'has_menu' => false,
                'price_list' => [],
            ],
        ];

        foreach ($zones as $z) {
            Zone::updateOrCreate(['name' => $z['name']], $z);
        }

        // 3. Professors (Hocametre)
        $professors = [
            ['name' => 'Prof. Dr. Eşref Adalı', 'department' => 'Bilgisayar Mühendisliği'],
            ['name' => 'Prof. Dr. Zehra Çataltepe', 'department' => 'Yapay Zeka ve Veri Mühendisliği'],
            ['name' => 'Doç. Dr. Sanem Sarıel', 'department' => 'Bilgisayar Mühendisliği'],
            ['name' => 'Prof. Dr. Celal Şengör', 'department' => 'Jeoloji Mühendisliği'],
            ['name' => 'Prof. Dr. Sıddık Yarman', 'department' => 'Elektrik-Elektronik Mühendisliği'],
        ];

        foreach ($professors as $p) {
            Professor::updateOrCreate(['name' => $p['name']], $p);
        }

        // 4. Initial Welcome Post
        Post::firstOrCreate(
            ['content' => 'PETEK V3 Kampüs Ağına Hoş Geldiniz! 🐝 Artık yeni ve ultra hızlı Laravel altyapımızla canlıdayız. #itü #petek #kampus'],
            [
                'user_id' => $admin->id,
                'post_type' => 'fun',
                'is_anonymous' => false,
                'hashtags' => ['itü', 'petek', 'kampus'],
                'view_count' => 120,
                'is_archived' => false,
            ]
        );
    }
}
