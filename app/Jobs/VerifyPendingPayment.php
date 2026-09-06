<?php
namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class VerifyPendingPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 25;

    public function __construct(public readonly int $paymentId) {}

    public function handle(PaymentService $service): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if (!$payment || !in_array($payment->status, ['creating','pending'], true) || !$payment->gateway_invoice_id) return;
        try { $service->verifyBluPal($payment); }
        catch (\Throwable) { /* recovery continues on a future cPanel tick */ }
    }
}
