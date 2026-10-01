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
