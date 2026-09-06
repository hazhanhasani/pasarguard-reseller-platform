<?php
namespace App\Payments;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;

final class BluPalClient
{
    private const BASE_URL = 'https://blupal.net/api';

    /** @return array<string,mixed> */
    public function createInvoice(int $amount): array
    {
        if ($amount < 100_000) throw new BluPalException('amount_too_low');
        $data = $this->request('POST', '/v1/invoices/create', ['amount' => $amount]);
        foreach (['invoice_id','amount','final_amount','status','payment_link','mode'] as $field) {
            if (!array_key_exists($field, $data)) throw new BluPalException('invalid_create_response');
        }
        if ((int) $data['amount'] !== $amount) throw new BluPalException('invoice_amount_mismatch');
        if (!in_array((string) $data['status'], ['PENDING','PAID','EXPIRED','CANCELED'], true)) throw new BluPalException('invalid_invoice_status');
        $this->assertPaymentLink((string) $data['payment_link']);
        return $data;
    }

    /** @return array<string,mixed> */
    public function invoice(int|string $invoiceId): array
    {
        $invoiceId = (string) $invoiceId;
        if (!ctype_digit($invoiceId) || (int) $invoiceId < 1) throw new BluPalException('invalid_invoice_id');
        $data = $this->request('GET', '/v1/invoices/'.$invoiceId);
        foreach (['invoice_id','status','amount','final_amount','mode'] as $field) {
            if (!array_key_exists($field, $data)) throw new BluPalException('invalid_status_response');
        }
        if ((string) $data['invoice_id'] !== $invoiceId) throw new BluPalException('invoice_identity_mismatch');
        if (!in_array((string) $data['status'], ['PENDING','PAID','EXPIRED','CANCELED'], true)) throw new BluPalException('invalid_invoice_status');
        return $data;
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, array $payload = []): array
    {
        $setting = PaymentGatewaySetting::query()->find('blupal');
        $apiKey = is_array($setting?->credentials) ? (string) ($setting->credentials['api_key'] ?? '') : '';
        if (!$setting || !$setting->enabled || $apiKey === '') throw new BluPalException('blupal_not_configured');
        if (!preg_match('/^blu_(?:test|live)_[A-Za-z0-9_-]+$/D', $apiKey)) throw new BluPalException('invalid_blupal_api_key');

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout(5)
                ->timeout(15)
                ->withOptions(['allow_redirects' => false]);
            $response = $method === 'GET'
                ? $request->get(self::BASE_URL.$path)
                : $request->post(self::BASE_URL.$path, $payload);
        } catch (\Throwable) {
            $this->markFailure($setting, 'transport_failed');
            throw new BluPalException('transport_failed');
        }

        if (!$response->successful()) {
            $reason = match ($response->status()) {
                401,403 => 'auth_failed',
                404 => 'not_found',
                422 => 'invalid_request',
                429 => 'rate_limited',
                default => 'http_failed',
            };
            $this->markFailure($setting, $reason);
            throw new BluPalException($reason, $response->status());
        }
        $data = $response->json();
        if (!is_array($data) || ($data['success'] ?? true) !== true) {
            $this->markFailure($setting, 'invalid_response');
            throw new BluPalException('invalid_response');
        }
        $setting->health = 'healthy';
        $setting->last_success_at = now();
        $setting->last_error = null;
        $setting->save();
        return $data;
    }

    private function markFailure(PaymentGatewaySetting $setting, string $reason): void
    {
        $setting->health = $reason === 'auth_failed' ? 'auth_error' : 'degraded';
        $setting->last_failure_at = now();
        $setting->last_error = $reason;
        $setting->save();
    }

    private function assertPaymentLink(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== 'blupal.net') {
            throw new BluPalException('invalid_payment_link');
        }
    }
}
