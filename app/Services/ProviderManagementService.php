<?php
namespace App\Services;

use App\Jobs\ReconcileSubscription;
use App\Jobs\RefreshProviderOutput;
use App\Jobs\SyncProviderUsage;
use App\Models\Provider;
use InvalidArgumentException;

final class ProviderManagementService
{
    public function create(string $name, string $apiUrl, string $apiKey, array $groupIds): Provider
    {
        return Provider::query()->create([
            'name' => $this->name($name),
            'adapter' => 'pasarguard',
            'api_url' => $apiUrl,
            'credentials' => ['key' => $apiKey],
            'group_ids' => $this->groups($groupIds),
            'mode' => 'disabled',
            'ready' => false,
            'health' => 'unknown',
            'health_score' => 0,
        ]);
    }

    public function update(Provider $provider, string $name, string $apiUrl, ?string $apiKey, array $groupIds): Provider
    {
        $changedConnection = $provider->api_url !== $apiUrl || ($apiKey !== null && $apiKey !== '');
        $provider->name = $this->name($name);
        $provider->api_url = $apiUrl;
        $provider->group_ids = $this->groups($groupIds);
        if ($apiKey !== null && $apiKey !== '') $provider->credentials = ['key' => $apiKey];
        if ($changedConnection) {
            $provider->ready = false;
            $provider->mode = 'disabled';
            $provider->health = 'unknown';
            $provider->health_score = 0;
            $provider->capabilities = null;
        }
        $provider->save();
        return $provider->fresh();
    }

    public function setMode(Provider $provider, string $mode): Provider
    {
        if (!in_array($mode, ['active','maintenance','disabled'], true)) throw new InvalidArgumentException('invalid_provider_mode');
        if ($mode === 'active' && !$provider->ready) throw new InvalidArgumentException('provider_not_ready');
        $provider->mode = $mode;
        if ($mode === 'maintenance') $provider->health = 'maintenance';
        $provider->save();
        return $provider->fresh();
    }

    public function forceSync(Provider $provider): int
    {
        $count = 0;
        foreach ($provider->mappings()->whereNotNull('provider_user_id')->pluck('id') as $mappingId) {
            SyncProviderUsage::dispatch((int) $mappingId)->onQueue('usage');
            RefreshProviderOutput::dispatch((int) $mappingId)->onQueue('output');
            $count += 2;
        }
        foreach ($provider->mappings()->distinct()->pluck('master_subscription_id') as $subscriptionId) {
            ReconcileSubscription::dispatch((string) $subscriptionId)->onQueue('reconcile');
            $count++;
        }
        return $count;
    }

    public function parseGroups(string $value): array
    {
        $parts = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $groups = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part) || (int) $part < 1) throw new InvalidArgumentException('invalid_group_id');
            $groups[] = (int) $part;
        }
        return $this->groups($groups);
    }

    private function groups(array $groups): array
    {
        $groups = array_values(array_unique(array_map('intval', $groups)));
        if ($groups === []) throw new InvalidArgumentException('provider_group_required');
        foreach ($groups as $group) if ($group < 1) throw new InvalidArgumentException('invalid_group_id');
        return $groups;
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('invalid_provider_name');
        return $name;
    }
}
