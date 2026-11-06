<?php

use App\Http\Controllers\ExportController;
use App\Livewire\Pages\Accounts;
use App\Livewire\Pages\Dues;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\Notifications;
use App\Livewire\Pages\Profile;
use App\Livewire\Pages\Reports;
use App\Livewire\Pages\Savings;
use App\Livewire\Pages\Transactions;
use Illuminate\Support\Facades\Route;

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::get('/transactions', Transactions::class)->name('transactions');
    Route::get('/reports', Reports::class)->name('reports');
    Route::get('/savings', Savings::class)->name('savings');
    Route::get('/dues', Dues::class)->name('dues');
    Route::get('/accounts', Accounts::class)->name('accounts');
    Route::get('/notifications', Notifications::class)->name('notifications');
    Route::get('/profile', Profile::class)->name('profile');

    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/export/json', [ExportController::class, 'json'])->name('export.json');
        Route::get('/export/csv', [ExportController::class, 'csv'])->name('export.csv');
    });
});

if (app()->environment('local')) {
    Route::view('/foundation', 'foundation');
}
