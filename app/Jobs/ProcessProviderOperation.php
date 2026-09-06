<?php
namespace App\Jobs;

use App\Models\MasterSubscription;
use App\Models\Provider;
use App\Models\ProviderOperation;
use App\Models\ProviderUserMapping;
use App\Providers\Adapters\ProviderException;
use App\Services\ProviderAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProcessProviderOperation implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 25;
    public int $uniqueFor = 300;

    public function __construct(public readonly int $operationId) {}
    public function uniqueId(): string { return (string) $this->operationId; }

    public function handle(ProviderAdapterFactory $factory): void
    {
        $operation = DB::transaction(function () {
            $op = ProviderOperation::query()->lockForUpdate()->find($this->operationId);
            if (!$op || $op->status === 'succeeded' || $op->status === 'failed') return null;
            if ($op->available_at && $op->available_at->isFuture()) return null;
            $op->status = 'processing';
            $op->attempts = (int) $op->attempts + 1;
            $op->last_attempt_at = now();
            $op->save();
            return $op->fresh();
        }, 5);
        if (!$operation) return;

        $provider = Provider::withTrashed()->find($operation->provider_id);
        $subscription = MasterSubscription::withTrashed()->find($operation->master_subscription_id);
        $mapping = ProviderUserMapping::query()
            ->where('master_subscription_id', $operation->master_subscription_id)
            ->where('provider_id', $operation->provider_id)
            ->first();
        if (!$provider || !$subscription || !$mapping) {
            $this->finishFailure($operation, 'operation_context_missing', true, $provider, $mapping);
            return;
        }
        if ($provider->trashed() || $provider->mode === 'disabled') {
            $this->finishFailure($operation, 'provider_disabled', false, $provider, $mapping);
            return;
        }
        if ($provider->mode === 'maintenance' && $operation->operation === 'create_user') {
            $this->finishFailure($operation, 'provider_maintenance', false, $provider, $mapping);
            return;
        }

        try {
            $adapter = $factory->make($provider);
            $payload = is_array($operation->payload) ? $operation->payload : [];
            switch ($operation->operation) {
                case 'create_user':
                    try {
                        $user = $adapter->readUser($mapping->provider_username);
                    } catch (ProviderException $e) {
                        if ($e->reason !== 'not_found') throw $e;
                        $user = $adapter->createUser(
                            $mapping->provider_username,
                            array_map('intval', (array) $provider->group_ids),
                            (int) ($payload['data_limit'] ?? $subscription->quota_bytes),
                            (int) ($payload['expire'] ?? ($subscription->expires_at?->getTimestamp() ?? 0)),
                        );
                    }
                    $providerUserId = $user['id'] ?? $user['user_id'] ?? null;
                    if ($providerUserId === null) {
                        $user = $adapter->readUser($mapping->provider_username);
                        $providerUserId = $user['id'] ?? $user['user_id'] ?? null;
                    }
                    if ($providerUserId === null) throw new RuntimeException('provider_user_id_missing');
                    $mapping->provider_user_id = (string) $providerUserId;
                    $mapping->actual_state = !empty($user['disabled']) ? 'disabled' : 'active';
                    break;

                case 'update_user':
                    $changes = [];
                    foreach (['data_limit','expire','group_ids','note'] as $field) if (array_key_exists($field, $payload)) $changes[$field] = $payload[$field];
                    if ($changes !== []) $adapter->modifyUser($mapping->provider_username, $changes);
                    break;

                case 'sync_state':
                    $desired = (string) ($payload['desired_state'] ?? $subscription->desired_state);
                    $disabled = $desired !== 'active';
                    $adapter->setDisabled($mapping->provider_username, $disabled);
                    $mapping->actual_state = $disabled ? 'disabled' : 'active';
                    break;

                case 'delete_user':
                    try { $adapter->deleteUser($mapping->provider_username); }
                    catch (ProviderException $e) { if ($e->reason !== 'not_found') throw $e; }
                    $mapping->actual_state = 'deleted';
                    break;

                case 'refresh_output':
                    if (!ctype_digit((string) $mapping->provider_user_id)) throw new RuntimeException('provider_user_id_invalid');
                    $mapping->last_valid_output = $adapter->configurations((int) $mapping->provider_user_id);
                    $mapping->last_valid_output_at = now();
                    break;

                case 'reset_usage':
                    $adapter->resetUsage($mapping->provider_username);
                    break;

                default:
                    throw new RuntimeException('unsupported_provider_operation');
            }

            DB::transaction(function () use ($operation, $provider, $mapping) {
                $op = ProviderOperation::query()->lockForUpdate()->findOrFail($operation->id);
                if ($op->status === 'succeeded') return;
                $op->status = 'succeeded';
                $op->last_error = null;
                $op->available_at = null;
                $op->save();
                $mapping->sync_status = 'synced';
                $mapping->last_success_at = now();
                $mapping->last_error = null;
                $mapping->last_error_at = null;
                $mapping->retry_count = 0;
                $mapping->save();
                $provider->health = $provider->health === 'maintenance' ? 'maintenance' : 'healthy';
                $provider->error_counter = 0;
                $provider->last_error = null;
                $provider->save();
            }, 5);
        } catch (ProviderException $e) {
            $permanent = in_array($e->reason, ['auth_or_permission_failed','missing_provider_api_key','invalid_provider_url','private_provider_address','invalid_group','invalid_create_parameters','unsupported_change'], true);
            $this->finishFailure($operation, $e->reason, $permanent, $provider, $mapping);
        } catch (\Throwable $e) {
            $reason = in_array($e->getMessage(), ['provider_user_id_missing','provider_user_id_invalid','unsupported_provider_operation'], true)
                ? $e->getMessage() : 'provider_operation_failed';
            $this->finishFailure($operation, $reason, true, $provider, $mapping);
        }
    }

    private function finishFailure(ProviderOperation $operation, string $reason, bool $permanent, ?Provider $provider, ?ProviderUserMapping $mapping): void
    {
        DB::transaction(function () use ($operation, $reason, $permanent, $provider, $mapping) {
            $op = ProviderOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $maxAttempts = max(1, (int) config('platform.provider_retry.max_attempts', 8));
            $shouldStop = $permanent || (int) $op->attempts >= $maxAttempts;
            $op->status = $shouldStop ? 'failed' : 'retrying';
            $op->last_error = $reason;
            if (!$shouldStop) {
                $base = max(1, (int) config('platform.provider_retry.base_seconds', 30));
                $max = max($base, (int) config('platform.provider_retry.max_seconds', 1800));
                $delay = min($max, $base * (2 ** min(10, max(0, (int) $op->attempts - 1))));
                $op->available_at = now()->addSeconds($delay);
            }
            $op->save();
            if ($mapping) {
                $mapping->sync_status = $shouldStop ? 'failed' : 'retrying';
                $mapping->last_error = $reason;
                $mapping->last_error_at = now();
                $mapping->retry_count = (int) $mapping->retry_count + 1;
                $mapping->save();
            }
            if ($provider) {
                $provider->error_counter = (int) $provider->error_counter + 1;
                $provider->last_failed_sync = now();
                $provider->last_error = $reason;
                $provider->health = $reason === 'auth_or_permission_failed' || $reason === 'missing_provider_api_key' ? 'auth_error' : ($reason === 'provider_maintenance' ? 'maintenance' : 'degraded');
                $provider->save();
            }
        }, 5);
    }
}
