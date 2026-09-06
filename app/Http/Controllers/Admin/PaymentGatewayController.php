<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PaymentGatewayController extends Controller
{
    public function edit()
    {
        $setting = PaymentGatewaySetting::query()->find('blupal');
        return view('admin.payments.gateway', compact('setting'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable','boolean'],
            'api_key' => ['nullable','string','max:512','regex:/^blu_(?:test|live)_[A-Za-z0-9_-]+$/'],
        ]);
        $setting = PaymentGatewaySetting::query()->firstOrNew(['gateway' => 'blupal']);
        $credentials = is_array($setting->credentials) ? $setting->credentials : [];
        if (!empty($data['api_key'])) $credentials['api_key'] = $data['api_key'];
        $enabled = (bool) ($data['enabled'] ?? false);
        if ($enabled && empty($credentials['api_key'])) {
            throw ValidationException::withMessages(['api_key' => 'برای فعال‌سازی BluPal ابتدا API Key را وارد کنید.']);
        }
        $setting->enabled = $enabled;
        $setting->credentials = $credentials;
        $setting->health = $setting->exists ? $setting->health : 'unknown';
        $setting->save();
        return back()->with('success', 'تنظیمات BluPal ذخیره شد. کلید در دیتابیس رمزنگاری می‌شود.');
    }
}
