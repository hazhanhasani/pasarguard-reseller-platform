<?php
namespace App\Services;

use App\Models\Provider;
use App\Providers\Adapters\ProviderException;

final class ProviderProbeService
{
    public function __construct(private readonly ProviderAdapterFactory $factory) {}

    /** @return array{ready:bool,health:string,latency_ms:int,capabilities:array<string,bool>,error:?string} */
    public function probe(Provider $provider): array
    {
        $started = hrtime(true);
        $capabilities = [
            'authentication' => false,
            'create_user' => false,
            'modify_user' => false,
            'disable_user' => false,
            'enable_user' => false,
            'delete_user' => false,
            'reset_usage' => false,
            'read_usage' => false,
            'subscription_support' => false,
            'json_subscription_output' => false,
        ];
        try {
            $this->factory->make($provider)->listUsers(0, 1);
            foreach (array_keys($capabilities) as $capability) $capabilities[$capability] = true;
            $latency = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
            $health = $latency >= 3000 ? 'slow' : 'healthy';
            $provider->health = $health;
            $provider->latency_ms = $latency;
            $provider->capabilities = $capabilities;
            $provider->error_counter = 0;
            $provider->last_error = null;
            $provider->save();
            return ['ready' => true, 'health' => $health, 'latency_ms' => $latency, 'capabilities' => $capabilities, 'error' => null];
        } catch (ProviderException $e) {
            $latency = max(0, (int) round((hrtime(true) - $started) / 1_000_000));
            $health = in_array($e->reason, ['auth_or_permission_failed','missing_provider_api_key'], true) ? 'auth_error' : 'offline';
            $provider->health = $health;
            $provider->latency_ms = $latency;
            $provider->capabilities = $capabilities;
            $provider->error_counter = (int) $provider->error_counter + 1;
            $provider->last_error = $e->reason;
            $provider->save();
            return ['ready' => false, 'health' => $health, 'latency_ms' => $latency, 'capabilities' => $capabilities, 'error' => $e->reason];
        }
    }
}
