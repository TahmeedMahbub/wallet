<?php

use Illuminate\Support\Facades\Route;

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'welcome')->name('home');
});

if (app()->environment('local')) {
    Route::view('/foundation', 'foundation');
}
