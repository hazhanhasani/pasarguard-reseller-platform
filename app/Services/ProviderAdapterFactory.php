<?php
namespace App\Services;

use App\Models\Provider;
use App\Providers\Adapters\PasarGuardProviderAdapter;
use App\Providers\Adapters\ProviderAdapterInterface;
use App\Providers\Adapters\ProviderException;

final class ProviderAdapterFactory
{
    public function make(Provider $provider): ProviderAdapterInterface
    {
        $credentials = $provider->credentials;
        $key = is_array($credentials) ? ($credentials['key'] ?? null) : null;
        if (!is_string($key) || $key === '') throw new ProviderException('missing_provider_api_key');
        return new PasarGuardProviderAdapter((string) $provider->api_url, $key);
    }
}
