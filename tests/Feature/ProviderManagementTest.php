<?php
namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Provider;
use App\Services\ProviderManagementService;
use App\Services\ProviderProbeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_probe_is_non_destructive_and_marks_provider_ready(): void
    {
        $provider = $this->provider();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/api/users')) return Http::response(['users' => [['id' => 7, 'username' => 'test_user']]]);
            if (str_contains($request->url(), '/api/user/7/subscription/xray')) return Http::response([['server' => 'one'], ['server' => 'one']]);
            return Http::response(['id' => 7, 'username' => 'test_user', 'used_traffic' => 123]);
        });

        $fresh = app(ProviderProbeService::class)->probe($provider);
        $this->assertTrue($fresh->ready);
        $this->assertSame('disabled', $fresh->mode);
        $this->assertTrue((bool) $fresh->capabilities['authentication']['verified']);
        $this->assertTrue((bool) $fresh->capabilities['read_usage']['verified']);
        $this->assertTrue((bool) $fresh->capabilities['json_subscription_output']['verified']);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        Http::assertSentCount(3);
    }

    public function test_provider_cannot_be_activated_before_successful_probe(): void
    {
        $provider = $this->provider();
        $this->expectException(InvalidArgumentException::class);
        app(ProviderManagementService::class)->setMode($provider, 'active');
    }

    public function test_repeated_probe_failure_is_deduplicated(): void
    {
        $provider = $this->provider();
        Http::fake(['*' => Http::response(['error' => 'denied'], 401)]);
        app(ProviderProbeService::class)->probe($provider);
        app(ProviderProbeService::class)->probe($provider->fresh());

        $this->assertSame(1, Notification::query()->where('event_key', 'provider.connection_failed')->count());
        $this->assertSame(2, (int) Notification::query()->where('event_key', 'provider.connection_failed')->value('occurrence_count'));
        $this->assertFalse($provider->fresh()->ready);
        $this->assertSame('auth_error', $provider->fresh()->health);
    }

    public function test_connection_change_forces_provider_back_to_disabled_not_ready(): void
    {
        $provider = $this->provider();
        $provider->ready = true;
        $provider->mode = 'active';
        $provider->save();

        $fresh = app(ProviderManagementService::class)->update($provider, 'P', 'https://8.8.8.8', 'new-secret-key', [1]);
        $this->assertFalse($fresh->ready);
        $this->assertSame('disabled', $fresh->mode);
    }

    private function provider(): Provider
    {
        return Provider::query()->create([
            'name' => 'P',
            'adapter' => 'pasarguard',
            'api_url' => 'https://1.1.1.1',
            'credentials' => ['key' => 'test-secret-key'],
            'group_ids' => [1],
            'mode' => 'disabled',
            'ready' => false,
            'health' => 'unknown',
        ]);
    }
}
