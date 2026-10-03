<?php

use Illuminate\Support\Facades\Route;

// Store Review & Legal Policy Routes (Google Play & App Store Compliance)
Route::get('/privacy', function () {
    return response()->file(public_path('privacy.html'));
});
Route::get('/gizlilik', function () {
    return response()->file(public_path('privacy.html'));
});
Route::get('/terms', function () {
    return response()->file(public_path('terms.html'));
});
Route::get('/kullanim-kosullari', function () {
    return response()->file(public_path('terms.html'));
});

// SPA (Single Page Application) - Serves the complete React Native / Expo Web App
Route::get('/{any?}', function ($any = null) {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
})->where('any', '^(?!api|storage).*$');
