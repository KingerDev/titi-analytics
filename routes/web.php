<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjednavkyController;
use App\Http\Controllers\ProduktController;
use App\Http\Controllers\RegistracieController;
use App\Http\Controllers\ZakaznikController;
use Illuminate\Support\Facades\Route;

// Auth routes (verejné)
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Analytika (chránené)
Route::middleware('analytics.auth')->group(function () {
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/registracie', [RegistracieController::class, 'index'])->name('registracie');

    Route::get('/objednavky', [ObjednavkyController::class, 'index'])->name('objednavky');
    Route::get('/objednavky/{id}', [ObjednavkyController::class, 'show'])->name('objednavky.show');

    Route::get('/produkty', [ProduktController::class, 'index'])->name('produkty');
    Route::get('/produkty/{id}', [ProduktController::class, 'show'])->name('produkty.show');

    Route::get('/zakaznici', [ZakaznikController::class, 'index'])->name('zakaznici');
    Route::get('/zakaznici/{id}', [ZakaznikController::class, 'show'])->name('zakaznici.show');

    // Polling endpoint pre in-app notifikácie
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
});
