<?php

use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\BookingController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['googleCalendarAuth'])->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('events.index');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/events/{event}/calendar', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/events/{event}/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/events/{event}', [BookingController::class, 'destroy'])->name('bookings.destroy');
});

Route::prefix( 'google')->controller(GoogleCalendarController::class)->group(function () {
    Route::get('/calendar', 'connectToGoogle')->name('google.connect');
    Route::get('/calendar/auth', 'redirectAuth')->name('google.auth');
    Route::get('/calendar/auth/callback', 'handleCallback')->name('google.callback');
    Route::get('/calendar/auth/revoke', 'revokeAccess')->name('google.revoke');
});

require __DIR__ . '/auth.php';
