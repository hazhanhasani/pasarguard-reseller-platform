<?php
namespace App\Jobs;

use App\Models\Provider;
use App\Models\ProviderUserMapping;
use App\Providers\Adapters\ProviderException;
use App\Services\ProviderAdapterFactory;
use App\Services\ProviderOperationService;
use App\Services\UsageAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SyncProviderUsage implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 25;
    public int $uniqueFor = 120;

    public function __construct(public readonly int $mappingId) {}
    public function uniqueId(): string { return (string) $this->mappingId; }

    public function handle(ProviderAdapterFactory $factory, UsageAccountingService $accounting, ProviderOperationService $operations): void
    {
        $mapping = ProviderUserMapping::query()->find($this->mappingId);
        if (!$mapping) return;
        $provider = Provider::query()->find($mapping->provider_id);
        if (!$provider || $provider->mode === 'disabled') return;

        try {
            $user = $factory->make($provider)->readUser($mapping->provider_username);
            [$counterField, $usage] = $this->extractCounter($user);
            $epoch = $counterField.':v1';
            $key = implode('|', ['usage', $mapping->id, $epoch, (int) $mapping->last_usage_bytes, $usage]);
            $snapshot = $accounting->record($mapping->id, $usage, $epoch, now(), $key);

            $mapping = $mapping->fresh();
            $mapping->actual_state = !empty($user['disabled']) ? 'disabled' : 'active';
            if ($snapshot->status === 'valid') {
                $mapping->sync_status = 'synced';
                $mapping->last_success_at = now();
            }
            $mapping->save();
            $provider->health = (int) $provider->latency_ms >= 3000 ? 'slow' : 'healthy';
            $provider->last_successful_sync = now();
            $provider->last_error = null;
            $provider->error_counter = 0;
            $provider->save();
        } catch (ProviderException $e) {
            $mapping->sync_status = $e->reason === 'not_found' ? 'missing' : 'stale';
            $mapping->last_error = $e->reason;
            $mapping->last_error_at = now();
            $mapping->retry_count = (int) $mapping->retry_count + 1;
            $mapping->save();
            $provider->last_failed_sync = now();
            $provider->last_error = $e->reason;
            $provider->error_counter = (int) $provider->error_counter + 1;
            $provider->health = $e->reason === 'auth_or_permission_failed' ? 'auth_error' : 'degraded';
            $provider->save();
            if ($e->reason === 'not_found') {
                $subscription = $mapping->subscription;
                if ($subscription) $operations->enqueue($subscription, (int) $provider->id, 'create_user');
            }
        }
    }

    /** @return array{string,int} */
    private function extractCounter(array $user): array
    {
        foreach (['lifetime_used_traffic', 'used_traffic'] as $field) {
            if (!array_key_exists($field, $user)) continue;
            $value = $user[$field];
            if (is_int($value) && $value >= 0) return [$field, $value];
            if (is_string($value) && ctype_digit($value)) {
                $int = (int) $value;
                if ((string) $int === ltrim($value, '0') || $int === 0) return [$field, $int];
            }
            throw new RuntimeException('provider_usage_invalid');
        }
        throw new RuntimeException('provider_usage_missing');
    }
}
