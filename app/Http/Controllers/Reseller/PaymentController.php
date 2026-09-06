<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Payments\BluPalException;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $resellerId = $this->resellerId($request);
        return view('reseller.wallet.index', [
            'wallet' => Wallet::query()->where('reseller_id', $resellerId)->firstOrFail(),
            'payments' => Payment::query()->where('reseller_id', $resellerId)->latest('id')->paginate(20),
            'transactions' => WalletLedger::query()->where('reseller_id', $resellerId)->latest('id')->limit(20)->get(),
        ]);
    }

    public function pay(Request $request, PaymentService $service)
    {
        $resellerId = $this->resellerId($request);
        $data = $request->validate(['amount' => ['required','integer','min:100000','max:9000000000000000000']]);
        try {
            $payment = $service->createBluPal($resellerId, (int) $data['amount']);
        } catch (BluPalException $e) {
            throw ValidationException::withMessages(['amount' => 'ایجاد پرداخت ممکن نشد: '.$e->reason]);
        }
        return redirect()->away((string) $payment->payment_url);
    }

    public function callback(Request $request, string $payment, PaymentService $service)
    {
        $resellerId = $this->resellerId($request);
        $model = Payment::query()->where('reseller_id', $resellerId)->where('public_id', $payment)->firstOrFail();
        if ($model->gateway_invoice_id && $model->status !== 'paid') {
            try { $model = $service->verifyBluPal($model); }
            catch (\Throwable) { $model = $model->fresh(); }
        }
        return view('reseller.wallet.callback', ['payment' => $model]);
    }

    private function resellerId(Request $request): int
    {
        $id = (int) $request->user()->reseller_id;
        abort_if($id < 1, 403);
        return $id;
    }
}
