<?php
namespace App\Services;

use App\Models\Payment;
use App\Payments\BluPalClient;
use App\Payments\BluPalException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PaymentService
{
    public function __construct(
        private readonly BluPalClient $blupal,
        private readonly WalletLedgerService $wallets,
    ) {}

    public function createBluPal(int $resellerId, int $amount): Payment
    {
        if ($amount < 100_000) throw new BluPalException('amount_too_low');
        $payment = Payment::query()->create([
            'public_id' => (string) Str::ulid(),
            'reseller_id' => $resellerId,
            'gateway' => 'blupal',
            'reference_id' => 'pay_'.bin2hex(random_bytes(16)),
            'amount' => $amount,
            'status' => 'creating',
        ]);

        try {
            $invoice = $this->blupal->createInvoice($amount);
            $payment->gateway_invoice_id = (string) $invoice['invoice_id'];
            $payment->final_amount = (int) $invoice['final_amount'];
            $payment->gateway_mode = (string) $invoice['mode'];
            $payment->status = $this->localStatus((string) $invoice['status']);
            $payment->payment_url = (string) $invoice['payment_link'];
            $payment->error_code = null;
            $payment->last_verified_at = now();
            $payment->save();
            return $payment->fresh();
        } catch (\Throwable $e) {
            $payment->status = 'failed';
            $payment->error_code = $e instanceof BluPalException ? $e->reason : 'payment_create_failed';
            $payment->save();
            throw $e;
        }
    }

    /** Server-to-server verification is authoritative; webhook payload values are not trusted for credit. */
    public function verifyBluPal(Payment $payment): Payment
    {
        if ($payment->gateway !== 'blupal' || !$payment->gateway_invoice_id) throw new BluPalException('invalid_payment_context');
        if ($payment->status === 'paid') return $payment;

        $remote = $this->blupal->invoice($payment->gateway_invoice_id);
        if ((int) $remote['amount'] !== (int) $payment->amount) {
            $this->markFailure($payment->id, 'amount_mismatch');
            throw new BluPalException('amount_mismatch');
        }
        if (isset($payment->final_amount) && $payment->final_amount !== null && (int) $remote['final_amount'] !== (int) $payment->final_amount) {
            $this->markFailure($payment->id, 'final_amount_mismatch');
            throw new BluPalException('final_amount_mismatch');
        }

        return DB::transaction(function () use ($payment, $remote) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'paid') return $locked;
            $status = (string) $remote['status'];
            $locked->last_verified_at = now();
            $locked->gateway_mode = (string) $remote['mode'];
            $locked->final_amount = (int) $remote['final_amount'];

            if ($status === 'PAID') {
                $transactionId = isset($remote['transaction_id']) ? (string) $remote['transaction_id'] : '';
                if ($transactionId === '') throw new LogicException('paid_invoice_missing_transaction_id');
                $this->wallets->credit(
                    (int) $locked->reseller_id,
                    (int) $locked->amount,
                    'online_payment',
                    'blupal:invoice:'.$locked->gateway_invoice_id,
                    'BluPal verified online payment',
                );
                $locked->gateway_transaction_id = $transactionId;
                $locked->status = 'paid';
                $locked->paid_at = now();
                $locked->error_code = null;
            } else {
                $locked->status = $this->localStatus($status);
                $locked->error_code = null;
            }
            $locked->save();
            return $locked->fresh();
        }, 5);
    }

    private function markFailure(int $paymentId, string $reason): void
    {
        Payment::query()->whereKey($paymentId)->update([
            'status' => 'failed',
            'error_code' => $reason,
            'last_verified_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function localStatus(string $remote): string
    {
        return match ($remote) {
            'PENDING' => 'pending',
            'PAID' => 'paid',
            'EXPIRED' => 'expired',
            'CANCELED' => 'canceled',
            default => throw new BluPalException('invalid_invoice_status'),
        };
    }
}
