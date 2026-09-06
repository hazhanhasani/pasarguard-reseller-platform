<?php
use App\Http\Controllers\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\PaymentGatewayController as AdminPaymentGatewayController;
use App\Http\Controllers\Admin\ProblemCenterController as AdminProblemCenterController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ResellerController as AdminResellerController;
use App\Http\Controllers\Admin\UpdateController as AdminUpdateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BluPalWebhookController;
use App\Http\Controllers\Reseller\DashboardController as ResellerDashboardController;
use App\Http\Controllers\Reseller\PaymentController as ResellerPaymentController;
use App\Http\Controllers\Reseller\ReportController as ResellerReportController;
use App\Http\Controllers\Reseller\StoreController;
use App\Http\Controllers\Reseller\SubscriptionController;
use App\Http\Controllers\SubscriptionGatewayController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/s/{token}', SubscriptionGatewayController::class)->where('token', '[A-Za-z0-9_-]{32,128}')->name('subscription.public');
Route::post('/webhooks/blupal', BluPalWebhookController::class)->middleware('throttle:120,1')->name('webhooks.blupal');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth','role:super_admin','write.guard'])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/resellers', [AdminResellerController::class, 'index'])->name('resellers.index');
    Route::get('/resellers/create', [AdminResellerController::class, 'create'])->name('resellers.create');
    Route::post('/resellers', [AdminResellerController::class, 'store'])->middleware('throttle:20,1')->name('resellers.store');
    Route::post('/resellers/{reseller}/topup', [AdminResellerController::class, 'topup'])->middleware('throttle:30,1')->name('resellers.topup');

    Route::get('/providers', [AdminProviderController::class, 'index'])->name('providers.index');
    Route::get('/providers/create', [AdminProviderController::class, 'create'])->name('providers.create');
    Route::post('/providers', [AdminProviderController::class, 'store'])->middleware('throttle:20,1')->name('providers.store');
    Route::get('/providers/{provider}/edit', [AdminProviderController::class, 'edit'])->name('providers.edit');
    Route::put('/providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
    Route::post('/providers/{provider}/test', [AdminProviderController::class, 'test'])->middleware('throttle:30,1')->name('providers.test');
    Route::post('/providers/{provider}/mode', [AdminProviderController::class, 'mode'])->name('providers.mode');
    Route::post('/providers/{provider}/force-sync', [AdminProviderController::class, 'forceSync'])->middleware('throttle:20,1')->name('providers.force-sync');
    Route::delete('/providers/{provider}', [AdminProviderController::class, 'destroy'])->name('providers.destroy');

    Route::get('/problems', [AdminProblemCenterController::class, 'index'])->name('problems.index');
    Route::post('/problems/{operation}/retry', [AdminProblemCenterController::class, 'retry'])->name('problems.retry');
    Route::post('/problems/retry-all', [AdminProblemCenterController::class, 'retryAll'])->middleware('throttle:10,1')->name('problems.retry-all');
    Route::post('/problems/subscriptions/{subscription}/reconcile', [AdminProblemCenterController::class, 'reconcile'])->name('problems.reconcile');

    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/resolve', [AdminNotificationController::class, 'resolve'])->name('notifications.resolve');

    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [AdminReportController::class, 'export'])->whereIn('format', ['csv','xls'])->middleware('throttle:30,1')->name('reports.export');

    Route::get('/backups', [AdminBackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [AdminBackupController::class, 'create'])->middleware('throttle:10,1')->name('backups.create');
    Route::put('/backups/retention', [AdminBackupController::class, 'retention'])->name('backups.retention');
    Route::get('/backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
    Route::post('/backups/{backup}/restore', [AdminBackupController::class, 'restore'])->middleware('throttle:3,1')->name('backups.restore');
    Route::delete('/backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('/updates', [AdminUpdateController::class, 'index'])->name('updates.index');
    Route::post('/updates', [AdminUpdateController::class, 'upload'])->middleware('throttle:10,1')->name('updates.upload');
    Route::post('/updates/{update}/apply', [AdminUpdateController::class, 'apply'])->middleware('throttle:3,1')->name('updates.apply');
    Route::delete('/updates/{update}/package', [AdminUpdateController::class, 'destroyPackage'])->name('updates.package.destroy');

    Route::get('/payments/gateway', [AdminPaymentGatewayController::class, 'edit'])->name('payments.gateway.edit');
    Route::put('/payments/gateway', [AdminPaymentGatewayController::class, 'update'])->middleware('throttle:20,1')->name('payments.gateway.update');
});

Route::prefix('reseller')->name('reseller.')->middleware(['auth','role:reseller','write.guard'])->group(function () {
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

    Route::get('/wallet', [ResellerPaymentController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/pay', [ResellerPaymentController::class, 'pay'])->middleware('throttle:20,1')->name('wallet.pay');
    Route::get('/wallet/payments/{payment}/callback', [ResellerPaymentController::class, 'callback'])->name('wallet.callback');

    Route::get('/reports', [ResellerReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [ResellerReportController::class, 'export'])->whereIn('format', ['csv','xls'])->middleware('throttle:30,1')->name('reports.export');
});
