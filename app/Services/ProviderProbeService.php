<?php
namespace App\Services;

use App\Models\Provider;
use App\Providers\Adapters\ProviderException;
use Illuminate\Support\Facades\DB;

final class ProviderProbeService
{
    public function __construct(
        private readonly ProviderAdapterFactory $factory,
        private readonly ProviderHealthService $health,
        private readonly NotificationService $notifications,
    ) {}

    /** Non-destructive live probe. No provider user is created, changed, disabled or deleted. */
    public function probe(Provider $provider): Provider
    {
        $capabilities = [
            'authentication' => ['supported' => true, 'verified' => false],
            'create_user' => ['supported' => true, 'verified' => false],
            'modify_user' => ['supported' => true, 'verified' => false],
            'disable_user' => ['supported' => true, 'verified' => false],
            'enable_user' => ['supported' => true, 'verified' => false],
            'delete_user' => ['supported' => true, 'verified' => false],
            'reset_usage' => ['supported' => true, 'verified' => false],
            'read_usage' => ['supported' => true, 'verified' => false],
            'subscription_support' => ['supported' => true, 'verified' => false],
            'json_subscription_output' => ['supported' => true, 'verified' => false],
        ];
        $started = microtime(true);
        try {
            $adapter = $this->factory->make($provider);
            $listed = $adapter->listUsers(0, 1);
            $capabilities['authentication']['verified'] = true;
            $users = $this->extractUsers($listed);
            if ($users !== []) {
                $first = $users[0];
                $username = is_array($first) ? (string) ($first['username'] ?? '') : '';
                $id = is_array($first) ? ($first['id'] ?? $first['user_id'] ?? null) : null;
                if ($username !== '') {
                    $read = $adapter->readUser($username);
                    $capabilities['read_usage']['verified'] = array_key_exists('used_traffic', $read)
                        || array_key_exists('lifetime_used_traffic', $read);
                    if ($id === null) $id = $read['id'] ?? $read['user_id'] ?? null;
                }
                if ($id !== null && ctype_digit((string) $id) && (int) $id > 0) {
                    $adapter->configurations((int) $id);
                    $capabilities['subscription_support']['verified'] = true;
                    $capabilities['json_subscription_output']['verified'] = true;
                }
            }
            $latency = max(0, (int) round((microtime(true) - $started) * 1000));
            DB::transaction(function () use ($provider, $capabilities, $latency) {
                $locked = Provider::query()->lockForUpdate()->findOrFail($provider->id);
                $locked->capabilities = $capabilities;
                $locked->ready = true;
                $locked->latency_ms = $latency;
                $locked->last_tested_at = now();
                $locked->capability_verified_at = now();
                $locked->last_successful_sync = now();
                $locked->last_error = null;
                $locked->error_counter = 0;
                $locked->save();
            }, 5);
            $fresh = $this->health->persist($provider->fresh());
            $this->notifications->resolve('provider.connection_failed', 'provider:'.$provider->id);
            return $fresh;
        } catch (ProviderException $e) {
            DB::transaction(function () use ($provider, $capabilities, $e) {
                $locked = Provider::query()->lockForUpdate()->findOrFail($provider->id);
                $locked->capabilities = $capabilities;
                $locked->ready = false;
                $locked->last_tested_at = now();
                $locked->last_failed_sync = now();
                $locked->last_error = $e->reason;
                $locked->error_counter = (int) $locked->error_counter + 1;
                $locked->health = $e->reason === 'auth_or_permission_failed' ? 'auth_error' : 'offline';
                $locked->health_score = 0;
                $locked->save();
            }, 5);
            $this->notifications->signal(
                'provider.connection_failed', 'provider:'.$provider->id,
                $e->reason === 'auth_or_permission_failed' ? 'critical' : 'error',
                'اختلال در Provider '.$provider->name,
                $e->reason,
            );
            return $provider->fresh();
        }
    }

    private function extractUsers(array $response): array
    {
        if (array_is_list($response)) return array_values(array_filter($response, 'is_array'));
        foreach (['users','items','data'] as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) {
                return array_values(array_filter($response[$key], 'is_array'));
            }
        }
        return [];
    }
}
