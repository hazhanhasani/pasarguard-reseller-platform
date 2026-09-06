<?php
use App\Jobs\ProcessProviderOperation;
use App\Jobs\ReconcileSubscription;
use App\Jobs\RefreshProviderOutput;
use App\Jobs\SyncProviderUsage;
use App\Jobs\VerifyPendingPayment;
use App\Models\Payment;
use App\Models\ProviderOperation;
use App\Models\ProviderUserMapping;
use App\Services\BackupService;
use App\Services\MaintenanceLockService;
use App\Services\OperationalHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('platform:tick', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->error('Tick requires MySQL/MariaDB advisory locking');
        return 1;
    }
    if (app(MaintenanceLockService::class)->isLocked()) {
        $this->info('Platform write maintenance is active; tick skipped without touching queued work.');
        return 0;
    }

    $name = 'platform:'.substr(hash('sha256', base_path()), 0, 40);
    $locked = DB::selectOne('SELECT GET_LOCK(?,0) AS acquired', [$name]);
    if ((int) $locked->acquired !== 1) {
        $this->info('Another tick is running');
        return 0;
    }

    $started = microtime(true);
    try {
        DB::table('settings')->updateOrInsert(['key' => 'cron_last_run'], ['value' => now()->toIso8601String(), 'updated_at' => now()]);

        ProviderOperation::query()
            ->whereIn('status', ['pending', 'retrying'])
            ->where(function ($q) { $q->whereNull('available_at')->orWhere('available_at', '<=', now()); })
            ->orderBy('id')->limit(max(1, (int) config('platform.tick.provider_operation_batch', 50)))
            ->pluck('id')->each(fn ($id) => ProcessProviderOperation::dispatch((int) $id)->onQueue('provider'));

        ProviderUserMapping::query()
            ->whereHas('provider', fn ($q) => $q->whereIn('mode', ['active', 'maintenance']))
            ->whereHas('subscription')
            ->orderByRaw('last_success_at IS NULL DESC, last_success_at ASC')->orderBy('id')
            ->limit(max(1, (int) config('platform.tick.usage_batch', 100)))
            ->pluck('id')->each(fn ($id) => SyncProviderUsage::dispatch((int) $id)->onQueue('usage'));

        ProviderUserMapping::query()
            ->whereNotNull('provider_user_id')
            ->whereHas('provider', fn ($q) => $q->whereIn('mode', ['active', 'maintenance']))
            ->whereHas('subscription', fn ($q) => $q->where('desired_state', 'active'))
            ->orderByRaw('last_valid_output_at IS NULL DESC, last_valid_output_at ASC')->orderBy('id')
            ->limit(max(1, (int) config('platform.tick.output_batch', 100)))
            ->pluck('id')->each(fn ($id) => RefreshProviderOutput::dispatch((int) $id)->onQueue('output'));

        DB::table('provider_user_mappings')
            ->select('master_subscription_id')->groupBy('master_subscription_id')
            ->orderByRaw('MIN(last_reconciled_at) IS NULL DESC, MIN(last_reconciled_at) ASC')
            ->limit(max(1, (int) config('platform.tick.reconcile_batch', 100)))
            ->pluck('master_subscription_id')->each(fn ($id) => ReconcileSubscription::dispatch((string) $id)->onQueue('reconcile'));

        Payment::query()
            ->where('gateway', 'blupal')->whereIn('status', ['creating','pending'])->whereNotNull('gateway_invoice_id')
            ->orderByRaw('last_verified_at IS NULL DESC, last_verified_at ASC')->orderBy('id')
            ->limit(max(1, (int) config('platform.tick.payment_batch', 50)))
            ->pluck('id')->each(fn ($id) => VerifyPendingPayment::dispatch((int) $id)->onQueue('payment'));

        $this->call('queue:work', [
            '--queue' => 'provider,usage,output,reconcile,payment,default',
            '--stop-when-empty' => true,
            '--max-jobs' => max(1, (int) config('platform.tick.queue_jobs', 200)),
            '--max-time' => max(5, (int) config('platform.tick.queue_max_time', 50)),
            '--tries' => 1,
        ]);

        app(OperationalHealthService::class)->run();

        DB::table('settings')->updateOrInsert(['key' => 'cron_last_finished'], ['value' => now()->toIso8601String(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'cron_last_duration_ms'], ['value' => (string) max(0, (int) round((microtime(true) - $started) * 1000)), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'cron_last_error'], ['value' => null, 'updated_at' => now()]);
    } catch (Throwable $e) {
        DB::table('settings')->updateOrInsert(['key' => 'cron_last_error'], ['value' => $e::class, 'updated_at' => now()]);
        $this->error('Tick failed: '.$e::class);
        return 1;
    } finally {
        DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
    }
    return 0;
})->purpose('Run one bounded provider/usage/output/billing/payment/reconciliation/health cycle; scheduling is controlled only by cPanel cron');

Artisan::command('platform:backup {type=full}', function (BackupService $backups, MaintenanceLockService $locks) {
    $type = (string) $this->argument('type');
    if (!in_array($type, ['database','persistent','full'], true)) {
        $this->error('Backup type must be database, persistent or full.');
        return 1;
    }
    if ($locks->isLocked()) {
        $this->error('Maintenance write lock is active; backup skipped.');
        return 1;
    }
    try {
        $backup = $backups->create($type, null, ['source'=>'cron']);
        $this->info('Backup #'.$backup->id.' completed ('.$backup->size_bytes.' bytes).');
        return 0;
    } catch (Throwable $e) {
        report($e);
        $this->error('Backup failed: '.$e::class);
        return 1;
    }
})->purpose('Create one backup cycle; cPanel Cron exclusively controls scheduling');
