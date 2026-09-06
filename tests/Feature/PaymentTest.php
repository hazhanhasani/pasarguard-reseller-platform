<?php
namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\Reseller;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verified_payment_credits_wallet_exactly_once(): void
    {
        [$reseller] = $this->context();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/v1/invoices/create')) {
                return Http::response([
                    'success' => true, 'invoice_id' => 123, 'amount' => 100000,
                    'final_amount' => 100123, 'status' => 'PENDING',
                    'payment_link' => 'https://blupal.net/payment/123', 'mode' => 'live',
                ]);
            }
            return Http::response([
                'success' => true, 'invoice_id' => 123, 'status' => 'PAID',
                'transaction_id' => 456, 'amount' => 100000, 'final_amount' => 100123,
                'mode' => 'live',
            ]);
        });

        $service = app(PaymentService::class);
        $payment = $service->createBluPal($reseller->id, 100000);
        $service->verifyBluPal($payment);
        $service->verifyBluPal($payment->fresh());

        $this->assertSame(100000, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));
        $this->assertSame(1, WalletLedger::query()->where('reference', 'blupal:invoice:123')->count());
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('456', $payment->fresh()->gateway_transaction_id);
    }

    public function test_webhook_payload_cannot_choose_credited_amount(): void
    {
        [$reseller] = $this->context();
        Http::fake([
            'https://blupal.net/api/v1/invoices/create' => Http::response([
                'success' => true, 'invoice_id' => 321, 'amount' => 100000,
                'final_amount' => 100777, 'status' => 'PENDING',
                'payment_link' => 'https://blupal.net/payment/321', 'mode' => 'live',
            ]),
        ]);
        $payment = app(PaymentService::class)->createBluPal($reseller->id, 100000);

        Http::fake([
            'https://blupal.net/api/v1/invoices/321' => Http::response([
                'success' => true, 'invoice_id' => 321, 'status' => 'PAID',
                'transaction_id' => 654, 'amount' => 100000, 'final_amount' => 100777,
                'mode' => 'live',
            ]),
        ]);

        $this->postJson('/webhooks/blupal', [
            'success' => true, 'event' => 'payment.completed', 'invoice_id' => 321,
            'status' => 'PAID', 'amount' => 999999999, 'final_amount' => 999999999,
        ])->assertOk()->assertJson(['received' => true]);

        $this->assertSame(100000, (int) Wallet::query()->where('reseller_id', $reseller->id)->value('balance'));
        $this->assertSame('paid', $payment->fresh()->status);
    }

    private function context(): array
    {
        PaymentGatewaySetting::query()->create([
            'gateway' => 'blupal', 'enabled' => true,
            'credentials' => ['api_key' => 'blu_live_TESTKEY123'], 'health' => 'unknown',
        ]);
        $reseller = Reseller::query()->create(['name' => 'نماینده پرداخت']);
        Wallet::query()->create(['reseller_id' => $reseller->id, 'balance' => 0]);
        return [$reseller];
    }
}
