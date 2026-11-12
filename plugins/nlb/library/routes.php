<?php

use Illuminate\Support\Facades\Route;
use Nlb\Library\Classes\Http\BookingController;
use Nlb\Library\Classes\Http\FeedbackController;
use Nlb\Library\Classes\Http\OrderController;
use Nlb\Library\Classes\Http\PwaController;
use Nlb\Library\Classes\Http\HealthController;
use Nlb\Library\Classes\Http\SeoController;
use Nlb\Library\Classes\Http\SubscriberController;

Route::get('health', [HealthController::class, 'index']);

Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::post('feedback', [FeedbackController::class, 'store']);
    Route::post('booking', [BookingController::class, 'store']);
    Route::post('order', [OrderController::class, 'store']);
    Route::post('subscribe', [SubscriberController::class, 'store']);
});

Route::middleware('web')->group(function () {
    Route::get('subscribe/confirm/{token}', [SubscriberController::class, 'confirm']);
    Route::get('unsubscribe/{token}', [SubscriberController::class, 'unsubscribe']);
    Route::get('sitemap.xml', [SeoController::class, 'sitemap']);
    Route::get('rss.xml', [SeoController::class, 'rss']);
    Route::get('robots.txt', [SeoController::class, 'robots']);
    Route::get('manifest.webmanifest', [PwaController::class, 'manifest']);
    Route::get('sw.js', [PwaController::class, 'serviceWorker']);
    Route::get('offline.html', [PwaController::class, 'offline']);
});
