<?php
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ResellerController as AdminResellerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Reseller\DashboardController as ResellerDashboardController;
use App\Http\Controllers\Reseller\StoreController;
use App\Http\Controllers\Reseller\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth','role:super_admin'])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/resellers', [AdminResellerController::class, 'index'])->name('resellers.index');
    Route::get('/resellers/create', [AdminResellerController::class, 'create'])->name('resellers.create');
    Route::post('/resellers', [AdminResellerController::class, 'store'])->middleware('throttle:20,1')->name('resellers.store');
    Route::post('/resellers/{reseller}/topup', [AdminResellerController::class, 'topup'])->middleware('throttle:30,1')->name('resellers.topup');
});

Route::prefix('reseller')->name('reseller.')->middleware(['auth','role:reseller'])->group(function () {
    Route::get('/', ResellerDashboardController::class)->name('dashboard');

    Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/create', [StoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [StoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store}', [StoreController::class, 'update'])->name('stores.update');
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');

    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('throttle:60,1')->name('subscriptions.store');
    Route::post('/subscriptions/{subscription}/suspend', [SubscriptionController::class, 'suspend'])->name('subscriptions.suspend');
    Route::post('/subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');
    Route::post('/subscriptions/{subscription}/volume', [SubscriptionController::class, 'addVolume'])->name('subscriptions.volume');
    Route::post('/subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');
    Route::post('/subscriptions/{subscription}/rotate-token', [SubscriptionController::class, 'rotateToken'])->name('subscriptions.rotate-token');
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
});
