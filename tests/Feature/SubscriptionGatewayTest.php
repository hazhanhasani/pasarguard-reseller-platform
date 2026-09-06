<?php
namespace Tests\Feature;

use App\Models\MasterSubscription;
use App\Models\Provider;
use App\Models\ProviderUserMapping;
use App\Models\Reseller;
use App\Models\Store;
use App\Models\Wallet;
use App\Services\SubscriptionTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionGatewayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_normal_browser_gets_landing_page(): void
    {
        [$subscription, $token] = $this->subscription();
        $response = $this->withHeaders([
            'Accept' => 'text/html,application/xhtml+xml',
            'User-Agent' => 'Mozilla/5.0 Chrome/140.0 Safari/537.36',
        ])->get('/s/'.$token);

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('فروشگاه تست')
            ->assertSee('اشتراک تست')
            ->assertDontSee('PasarGuard');
    }

    public function test_subscription_client_gets_all_cached_configs_without_deduplication_or_filtering(): void
    {
        [$subscription, $token] = $this->subscription();
        $provider = Provider::query()->create([
            'name' => 'Internal Provider Secret',
            'api_url' => 'https://1.1.1.1',
            'credentials' => ['key' => 'secret'],
            'group_ids' => [1],
            'mode' => 'active',
            'health' => 'healthy',
        ]);
        $configs = [
            ['remarks' => 'same', 'server' => 'one.example'],
            ['remarks' => 'same', 'server' => 'one.example'],
            ['invalid' => true],
        ];
        ProviderUserMapping::query()->create([
            'master_subscription_id' => $subscription->master_subscription_id,
            'provider_id' => $provider->id,
            'provider_user_id' => '10',
            'provider_username' => 'u_gatewaytest',
            'sync_status' => 'synced',
            'actual_state' => 'active',
            'usage_epoch' => 'initial',
            'last_valid_output' => $configs,
            'last_valid_output_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Hiddify/3.0',
        ])->get('/s/'.$token);

        $response->assertOk()->assertHeader('X-Subscription-State', 'active');
        $this->assertSame($configs, $response->json());
        $this->assertStringNotContainsString('Internal Provider Secret', $response->getContent());
    }

    public function test_inactive_subscription_never_serves_configs_to_client(): void
    {
        [$subscription, $token] = $this->subscription('wallet_zero');
        $response = $this->withHeaders(['Accept' => 'application/json'])->get('/s/'.$token);
        $response->assertStatus(403)->assertJson([
            'error' => 'subscription_inactive',
            'status' => 'wallet_zero',
        ]);
    }

    public function test_ambiguous_request_falls_back_to_html(): void
    {
        [$subscription, $token] = $this->subscription();
        $this->withHeaders(['Accept' => '*/*', 'User-Agent' => 'curl/8.0'])
            ->get('/s/'.$token)
            ->assertOk()
            ->assertSee('اشتراک تست');
    }

    public function test_revoked_token_is_immediately_invalid(): void
    {
        [$subscription, $token] = $this->subscription();
        app(SubscriptionTokenService::class)->revoke($subscription->master_subscription_id);
        $this->get('/s/'.$token)->assertNotFound();
    }

    private function subscription(string $state = 'active'): array
    {
        $reseller = Reseller::query()->create(['name' => 'نماینده تست']);
        $store = Store::query()->create([
            'reseller_id' => $reseller->id,
            'name' => 'فروشگاه تست',
            'brand_color' => '#4f7cff',
        ]);
        Wallet::query()->create(['reseller_id' => $reseller->id, 'balance' => 100000]);
        $issued = app(SubscriptionTokenService::class)->issue();
        $subscription = MasterSubscription::query()->create([
            'master_subscription_id' => (string) \Illuminate\Support\Str::ulid(),
            'reseller_id' => $reseller->id,
            'store_id' => $store->id,
            'name' => 'اشتراک تست',
            'public_token_hash' => $issued['hash'],
            'public_token_encrypted' => $issued['plain'],
            'token_version' => 1,
            'quota_bytes' => 50_000_000_000,
            'used_bytes' => 10_000_000_000,
            'duration_seconds' => 30 * 86400,
            'expires_at' => now()->addDays(20),
            'manual_suspended' => false,
            'desired_state' => $state,
            'state_version' => 1,
            'last_state_change_at' => now(),
        ]);
        return [$subscription, $issued['plain']];
    }
}
