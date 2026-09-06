<?php
namespace Tests\Feature;

use App\Models\MasterSubscription;
use App\Models\Provider;
use App\Models\Reseller;
use App\Models\Store;
use App\Models\UsageCharge;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\MasterSubscriptionService;
use App\Services\PriceService;
use App\Services\SubscriptionTokenService;
use App\Services\UsageAccountingService;
use App\Services\WalletLedgerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AccountingCoreTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(int $balance = 1000): array
    {
        $reseller = Reseller::query()->create(['name' => 'R']);
        $store = Store::query()->create(['reseller_id' => $reseller->id, 'name' => 'S']);
        Wallet::query()->create(['reseller_id' => $reseller->id, 'balance' => $balance, 'fractional_numerator' => 0]);
        return [$reseller, $store];
    }

    public function test_wallet_reference_is_idempotent_and_recharge_only_clears_wallet_zero(): void
    {
        [$reseller, $store] = $this->tenant(100);
        $subscriptions = app(MasterSubscriptionService::class);
        $first = $subscriptions->create($reseller->id, $store->id, 'one', 0, 0)['subscription'];
        $second = $subscriptions->create($reseller->id, $store->id, 'two', 0, 0)['subscription'];
        $subscriptions->suspend($second->master_subscription_id);

        $wallets = app(WalletLedgerService::class);
        $entry = $wallets->debit($reseller->id, 100, 'usage_charge', 'test:debit');
        $same = $wallets->debit($reseller->id, 100, 'usage_charge', 'test:debit');
        $this->assertSame($entry->id, $same->id);
        $this->assertSame(0, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));
        $this->assertSame('wallet_zero', MasterSubscription::query()->findOrFail($first->master_subscription_id)->desired_state);
        $this->assertSame('manual_suspended', MasterSubscription::query()->findOrFail($second->master_subscription_id)->desired_state);

        $wallets->credit($reseller->id, 100, 'manual_topup', 'test:credit');
        $this->assertSame('active', MasterSubscription::query()->findOrFail($first->master_subscription_id)->desired_state);
        $this->assertSame('manual_suspended', MasterSubscription::query()->findOrFail($second->master_subscription_id)->desired_state);
        $this->assertCount(2, WalletLedger::query()->where('reseller_id', $reseller->id)->get());
    }

    public function test_usage_delta_billing_is_atomic_precise_and_preserves_rate_history(): void
    {
        [$reseller, $store] = $this->tenant(10_000);
        $provider = Provider::query()->create(['name' => 'P', 'api_url' => 'https://1.1.1.1', 'credentials' => ['key' => 'secret'], 'group_ids' => [1]]);
        $provider->mode = 'active';
        $provider->save();
        $prices = app(PriceService::class);
        $firstBoundary = now()->subMinutes(2)->startOfSecond();
        $prices->setGlobalRate(1000, $firstBoundary);
        $created = app(MasterSubscriptionService::class)->create($reseller->id, $store->id, 'metered', 0, 0)['subscription'];
        $mapping = $created->mappings()->firstOrFail();
        $accounting = app(UsageAccountingService::class);

        $at1 = now()->subMinute()->startOfSecond();
        $s1 = $accounting->record($mapping->id, 1_000_000, 'initial', $at1, 'sample-one');
        $again = $accounting->record($mapping->id, 1_000_000, 'initial', $at1, 'sample-one');
        $this->assertSame($s1->id, $again->id);
        $this->assertSame(9_999, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));

        $secondBoundary = now()->startOfSecond();
        $prices->setGlobalRate(2000, $secondBoundary);
        $accounting->record($mapping->id, 2_000_000, 'initial', now()->addSecond(), 'sample-two');
        $this->assertSame(9_997, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));
        $this->assertSame([1000, 2000], UsageCharge::query()->orderBy('id')->pluck('rate_minor_per_gb')->map(fn ($v) => (int) $v)->all());

        $before = (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance');
        $regression = $accounting->record($mapping->id, 1_500_000, 'initial', now()->addSeconds(2), 'counter-regression');
        $this->assertSame('reconcile_required', $regression->status);
        $this->assertSame($before, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));
    }

    public function test_token_rotation_immediately_invalidates_old_link_and_database_does_not_store_plain_token(): void
    {
        [$reseller, $store] = $this->tenant();
        $created = app(MasterSubscriptionService::class)->create($reseller->id, $store->id, 'token-test', 0, 0);
        $tokens = app(SubscriptionTokenService::class);
        $this->assertNotNull($tokens->resolve($created['token']));
        $raw = DB::table('master_subscriptions')->where('master_subscription_id', $created['subscription']->master_subscription_id)->value('public_token_encrypted');
        $this->assertStringNotContainsString($created['token'], (string) $raw);

        $new = $tokens->rotate($created['subscription']->master_subscription_id);
        $this->assertNull($tokens->resolve($created['token']));
        $this->assertNotNull($tokens->resolve($new));
    }

    public function test_store_tenant_boundary_is_enforced_by_service(): void
    {
        [$r1] = $this->tenant();
        [$r2, $s2] = $this->tenant();
        $this->expectException(ModelNotFoundException::class);
        app(MasterSubscriptionService::class)->create($r1->id, $s2->id, 'forbidden', 0, 0);
    }

    public function test_wallet_ledger_is_immutable(): void
    {
        [$reseller] = $this->tenant();
        $entry = app(WalletLedgerService::class)->credit($reseller->id, 1, 'adjustment', 'immutability');
        $this->expectException(LogicException::class);
        $entry->update(['description' => 'changed']);
    }
}
