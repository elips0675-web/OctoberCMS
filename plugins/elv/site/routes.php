<?php

use Illuminate\Support\Facades\Route;
use Elv\Site\Classes\Http\ApiController;
use Elv\Site\Classes\Http\BookingController;
use Elv\Site\Classes\Http\FeedbackController;
use Elv\Site\Classes\Http\OrderController;
use Elv\Site\Classes\Http\PwaController;
use Elv\Site\Classes\Http\HealthController;
use Elv\Site\Classes\Http\SeoController;
use Elv\Site\Classes\Http\SubscriberController;

Route::get('health', [HealthController::class, 'index']);

Route::middleware('throttle:60,1')->group(function () {
    Route::get('api/v1/{handle}', [ApiController::class, 'index']);
    Route::get('api/v1/{handle}/{slug}', [ApiController::class, 'show']);
});

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
    Route::get('blog/rss.xml', [SeoController::class, 'blogRss']);
    Route::get('robots.txt', [SeoController::class, 'robots']);
    Route::get('manifest.webmanifest', [PwaController::class, 'manifest']);
    Route::get('sw.js', [PwaController::class, 'serviceWorker']);
    Route::get('offline.html', [PwaController::class, 'offline']);
});
