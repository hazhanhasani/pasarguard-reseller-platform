<?php
namespace App\Services;

use App\Models\Backup;
use App\Models\Notification;
use App\Models\PaymentGatewaySetting;
use App\Models\Provider;
use App\Models\UpdateHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SystemHealthService
{
    public function __construct(private readonly MaintenanceLockService $locks) {}

    public function collect(): array
    {
        $cronRaw = DB::table('settings')->where('key', 'cron_last_finished')->value('value');
        $cronAt = null; $cronAge = null;
        if (is_string($cronRaw) && $cronRaw !== '') {
            try { $cronAt = Carbon::parse($cronRaw); $cronAge = max(0, now()->diffInSeconds($cronAt, true)); }
            catch (\Throwable) { $cronAt = null; }
        }
        try { $dbVersion = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? 'unknown'); }
        catch (\Throwable) { $dbVersion = 'unavailable'; }

        $migrationFiles = [];
        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) $migrationFiles[] = pathinfo($file, PATHINFO_FILENAME);
        sort($migrationFiles);
        $applied = DB::table('migrations')->pluck('migration')->map(fn ($v)=>(string)$v)->all();
        $pending = array_values(array_diff($migrationFiles, $applied));

        $providerRows = Provider::query()->orderBy('id')->get()->map(fn (Provider $provider) => [
            'id' => (int) $provider->id,
            'name' => (string) $provider->name,
            'mode' => (string) $provider->mode,
            'health' => (string) $provider->health,
            'health_score' => (int) ($provider->health_score ?? 0),
            'latency_ms' => $provider->latency_ms === null ? null : (int) $provider->latency_ms,
            'error_counter' => (int) $provider->error_counter,
            'last_successful_sync' => $provider->last_successful_sync?->toIso8601String(),
            'last_failed_sync' => $provider->last_failed_sync?->toIso8601String(),
        ])->all();

        $gateway = PaymentGatewaySetting::query()->find('blupal');
        $lastBackup = Backup::query()->where('status','completed')->latest('id')->first();
        $lastUpdate = UpdateHistory::query()->latest('id')->first();
        $writable = [];
        foreach (['storage'=>storage_path(), 'bootstrap/cache'=>base_path('bootstrap/cache'), 'public/uploads'=>public_path('uploads')] as $label=>$path) {
            $check = is_dir($path) ? $path : dirname($path);
            $writable[$label] = is_dir($check) && is_writable($check);
        }
        $extensions = [];
        foreach (['pdo_mysql','mbstring','openssl','curl','zip','fileinfo'] as $extension) $extensions[$extension] = extension_loaded($extension);

        return [
            'generated_at' => now()->toIso8601String(),
            'version' => (string) (DB::table('settings')->where('key','app_version')->value('value') ?? config('platform.version','dev')),
            'php' => ['version'=>PHP_VERSION,'is_64bit'=>PHP_INT_SIZE===8,'extensions'=>$extensions],
            'database' => ['driver'=>DB::connection()->getDriverName(),'version'=>$dbVersion],
            'disk' => ['free_bytes'=>(int)(disk_free_space(storage_path()) ?: 0),'total_bytes'=>(int)(disk_total_space(storage_path()) ?: 0)],
            'writable' => $writable,
            'cron' => ['last_finished'=>$cronAt?->toIso8601String(),'age_seconds'=>$cronAge,'last_error'=>DB::table('settings')->where('key','cron_last_error')->value('value')],
            'queue' => ['backlog'=>(int)DB::table('jobs')->count(),'failed'=>(int)DB::table('failed_jobs')->count()],
            'migrations' => ['applied_count'=>count($applied),'file_count'=>count($migrationFiles),'pending'=>$pending],
            'backup' => $lastBackup ? ['id'=>(int)$lastBackup->id,'type'=>$lastBackup->type,'completed_at'=>$lastBackup->completed_at?->toIso8601String(),'size_bytes'=>(int)$lastBackup->size_bytes] : null,
            'update' => $lastUpdate ? ['id'=>(int)$lastUpdate->id,'version'=>$lastUpdate->version,'status'=>$lastUpdate->status,'completed_at'=>$lastUpdate->completed_at?->toIso8601String()] : null,
            'maintenance' => ['write_lock'=>$this->locks->isLocked(),'reason'=>$this->locks->reason()],
            'providers' => $providerRows,
            'blupal' => $gateway ? [
                'configured'=>is_array($gateway->credentials) && !empty($gateway->credentials['api_key']),
                'enabled'=>(bool)$gateway->enabled,
                'last_success_at'=>$gateway->last_success_at?->toIso8601String(),
                'last_failure_at'=>$gateway->last_failure_at?->toIso8601String(),
            ] : ['configured'=>false,'enabled'=>false,'last_success_at'=>null,'last_failure_at'=>null],
            'recent_errors' => Notification::query()->whereNull('resolved_at')->latest('last_seen_at')->limit(25)->get()->map(fn (Notification $n)=>[
                'event_key'=>(string)$n->event_key,
                'severity'=>(string)$n->severity,
                'audience_type'=>(string)$n->audience_type,
                'reseller_id'=>$n->reseller_id === null ? null : (int)$n->reseller_id,
                'occurrences'=>(int)$n->occurrence_count,
                'last_seen_at'=>$n->last_seen_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
