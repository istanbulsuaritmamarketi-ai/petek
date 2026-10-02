<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\Api\EventController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('itu:crawl-events', function () {
    $this->info('Starting ITU events crawl...');
    $controller = new EventController();
    $response = $controller->crawlItuEvents();
    $data = json_decode($response->getContent(), true);
    $this->info($data['message'] ?? 'Etkinlikler güncellendi.');
})->purpose('Crawl upcoming events from etkinlik.itu.edu.tr');

Schedule::command('itu:crawl-events')->dailyAt('06:00');

Artisan::command('vibes:clean', function () {
    $count = \App\Models\CampusVibe::where('expires_at', '<=', now())
        ->orWhere('created_at', '<=', now()->subHours(24))
        ->delete();
    $this->info("{$count} süresi dolmuş vibe veritabanından temizlendi.");
})->purpose('24 saati geçmiş tüm kampüs vibelarını otomatik siler');

Schedule::command('vibes:clean')->hourly();
