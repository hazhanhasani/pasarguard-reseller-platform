<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

final class BluPalWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $service)
    {
        $payload = $request->json()->all();
        if (($payload['event'] ?? null) !== 'payment.completed' || ($payload['status'] ?? null) !== 'PAID') {
            return response()->json(['received' => false, 'error' => 'invalid_event'], 400);
        }
        $invoiceId = (string) ($payload['invoice_id'] ?? '');
        if (!ctype_digit($invoiceId) || (int) $invoiceId < 1) {
            return response()->json(['received' => false, 'error' => 'invalid_invoice'], 400);
        }
        $payment = Payment::query()->where('gateway', 'blupal')->where('gateway_invoice_id', $invoiceId)->first();
        if (!$payment) {
            // Do not reveal local payment inventory to an unauthenticated caller.
            return response()->json(['received' => true], 200);
        }
        try {
            $service->verifyBluPal($payment);
        } catch (\Throwable) {
            // BluPal currently documents no webhook signature; verification failure must never credit the wallet.
            return response()->json(['received' => false, 'error' => 'verification_failed'], 503);
        }
        return response()->json(['received' => true]);
    }
}
