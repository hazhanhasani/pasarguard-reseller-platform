<?php
namespace App\Jobs;

use App\Models\Provider;
use App\Models\ProviderUserMapping;
use App\Providers\Adapters\ProviderException;
use App\Services\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RefreshProviderOutput implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 25;

    public function __construct(public readonly int $mappingId) {}

    public function handle(ProviderAdapterFactory $factory): void
    {
        $mapping = ProviderUserMapping::query()->find($this->mappingId);
        if (!$mapping) return;
        $provider = Provider::query()->find($mapping->provider_id);
        if (!$provider || !in_array($provider->mode, ['active','maintenance'], true)) return;
        if (!ctype_digit((string) $mapping->provider_user_id)) return;

        try {
            $started = microtime(true);
            $output = $factory->make($provider)->configurations((int) $mapping->provider_user_id);
            $latency = max(0, (int) round((microtime(true) - $started) * 1000));

            DB::transaction(function () use ($mapping, $provider, $output, $latency) {
                $fresh = ProviderUserMapping::query()->lockForUpdate()->find($mapping->id);
                if (!$fresh) return;
                $fresh->last_valid_output = $output;
                $fresh->last_valid_output_at = now();
                $fresh->last_success_at = now();
                if ($fresh->sync_status === 'stale') $fresh->sync_status = 'synced';
                $fresh->last_error = null;
                $fresh->last_error_at = null;
                $fresh->save();

                $p = Provider::query()->lockForUpdate()->find($provider->id);
                if ($p) {
                    $p->latency_ms = $latency;
                    $p->last_successful_sync = now();
                    $p->last_error = null;
                    $p->error_counter = 0;
                    $p->health = $p->mode === 'maintenance' ? 'maintenance' : ($latency >= 3000 ? 'slow' : 'healthy');
                    $p->save();
                }
            }, 5);
        } catch (ProviderException $e) {
            $this->recordFailure($mapping->id, $provider->id, $e->reason);
        } catch (\Throwable $e) {
            $reason = $e instanceof RuntimeException && in_array($e->getMessage(), ['provider_user_id_invalid'], true)
                ? $e->getMessage() : 'subscription_output_refresh_failed';
            $this->recordFailure($mapping->id, $provider->id, $reason);
        }
    }

    private function recordFailure(int $mappingId, int $providerId, string $reason): void
    {
        DB::transaction(function () use ($mappingId, $providerId, $reason) {
            $mapping = ProviderUserMapping::query()->lockForUpdate()->find($mappingId);
            if ($mapping) {
                // Never erase last_valid_output on a transient Provider failure.
                $mapping->sync_status = 'stale';
                $mapping->last_error = $reason;
                $mapping->last_error_at = now();
                $mapping->save();
            }
            $provider = Provider::query()->lockForUpdate()->find($providerId);
            if ($provider) {
                $provider->error_counter = (int) $provider->error_counter + 1;
                $provider->last_failed_sync = now();
                $provider->last_error = $reason;
                $provider->health = in_array($reason, ['auth_or_permission_failed','missing_provider_api_key'], true) ? 'auth_error' : 'degraded';
                $provider->save();
            }
        }, 5);
    }
}
