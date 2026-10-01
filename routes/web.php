<?php

use Illuminate\Support\Facades\Route;

// SPA (Single Page Application) - Serves the complete React Native / Expo Web App
Route::get('/{any?}', function ($any = null) {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
})->where('any', '^(?!api|storage).*$');
