<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\MasterSubscription;
use App\Models\Store;
use App\Services\MasterSubscriptionService;
use App\Services\SubscriptionTokenService;
use App\Services\UnitParser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $resellerId = $this->resellerId($request);
        $query = MasterSubscription::query()->where('reseller_id', $resellerId)->with('store');
        if (($q = trim((string) $request->query('q'))) !== '') $query->where('name', 'like', '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%');
        $allowedStates = ['active','manual_suspended','wallet_zero','quota_exceeded','expired'];
        if (in_array($request->query('state'), $allowedStates, true)) $query->where('desired_state', $request->query('state'));
        if ($request->filled('store')) {
            $storeId = (int) $request->query('store');
            Store::query()->where('reseller_id', $resellerId)->findOrFail($storeId);
            $query->where('store_id', $storeId);
        }
        $subscriptions = $query->latest('created_at')->paginate(25)->withQueryString();
        foreach ($subscriptions as $subscription) {
            $subscription->public_url = $subscription->token_revoked_at ? null : url('/s/'.$subscription->public_token_encrypted);
        }
        $stores = Store::query()->where('reseller_id', $resellerId)->orderBy('name')->get();
        return view('reseller.subscriptions.index', compact('subscriptions','stores'));
    }

    public function create(Request $request)
    {
        $resellerId = $this->resellerId($request);
        $stores = Store::query()->where('reseller_id', $resellerId)->orderBy('name')->get();
        if ($stores->isEmpty()) return redirect()->route('reseller.stores.create')->with('error', 'ابتدا یک فروشگاه بسازید.');
        return view('reseller.subscriptions.create', compact('stores'));
    }

    public function store(Request $request, MasterSubscriptionService $service)
    {
        $resellerId = $this->resellerId($request);
        $stores = Store::query()->where('reseller_id', $resellerId)->orderBy('id')->get();
        if ($stores->isEmpty()) throw ValidationException::withMessages(['store_id' => 'ابتدا یک فروشگاه بسازید.']);
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'store_id' => ['nullable','integer'],
            'volume_gb' => ['required','regex:/^\d+(?:\.\d{1,9})?$/'],
            'duration_days' => ['required','integer','min:0','max:36500'],
        ]);
        $storeId = $stores->count() === 1 ? (int) $stores->first()->id : (int) ($data['store_id'] ?? 0);
        if (!$stores->contains('id', $storeId)) throw ValidationException::withMessages(['store_id' => 'فروشگاه معتبر نیست.']);
        try {
            $bytes = UnitParser::decimalGbToBytes((string) $data['volume_gb']);
            $seconds = UnitParser::daysToSeconds((int) $data['duration_days']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['volume_gb' => 'حجم یا مدت واردشده معتبر نیست.']);
        }
        $created = $service->create($resellerId, $storeId, $data['name'], $bytes, $seconds);
        return redirect()->route('reseller.subscriptions.index')
            ->with('success', 'اشتراک ساخته شد و همگام‌سازی Providerها در صف قرار گرفت.')
            ->with('new_subscription_link', url('/s/'.$created['token']));
    }

    public function suspend(Request $request, string $subscription, MasterSubscriptionService $service)
    {
        $owned = $this->owned($request, $subscription);
        $service->suspend($owned->master_subscription_id);
        return back()->with('success', 'اشتراک تعلیق شد.');
    }

    public function reactivate(Request $request, string $subscription, MasterSubscriptionService $service)
    {
        $owned = $this->owned($request, $subscription);
        $service->reactivate($owned->master_subscription_id);
        return back()->with('success', 'وضعیت اشتراک دوباره ارزیابی شد.');
    }

    public function addVolume(Request $request, string $subscription, MasterSubscriptionService $service)
    {
        $owned = $this->owned($request, $subscription);
        $data = $request->validate(['volume_gb' => ['required','regex:/^\d+(?:\.\d{1,9})?$/']]);
        try { $bytes = UnitParser::decimalGbToBytes((string) $data['volume_gb']); }
        catch (\Throwable) { throw ValidationException::withMessages(['volume_gb' => 'حجم معتبر نیست.']); }
        if ($bytes <= 0) throw ValidationException::withMessages(['volume_gb' => 'حجم افزوده باید بیشتر از صفر باشد.']);
        $service->addVolume($owned->master_subscription_id, $bytes);
        return back()->with('success', 'حجم افزوده شد.');
    }

    public function extend(Request $request, string $subscription, MasterSubscriptionService $service)
    {
        $owned = $this->owned($request, $subscription);
        $data = $request->validate(['days' => ['required','integer','min:1','max:36500']]);
        $service->extend($owned->master_subscription_id, UnitParser::daysToSeconds((int) $data['days']));
        return back()->with('success', 'زمان اشتراک تمدید شد.');
    }

    public function rotateToken(Request $request, string $subscription, SubscriptionTokenService $tokens)
    {
        $owned = $this->owned($request, $subscription);
        $token = $tokens->rotate($owned->master_subscription_id);
        return back()->with('success', 'لینک اشتراک تعویض شد؛ لینک قبلی فوراً نامعتبر است.')->with('new_subscription_link', url('/s/'.$token));
    }

    public function destroy(Request $request, string $subscription, MasterSubscriptionService $service)
    {
        $owned = $this->owned($request, $subscription);
        $service->delete($owned->master_subscription_id);
        return back()->with('success', 'اشتراک آرشیو و حذف آن از Providerها در صف قرار گرفت.');
    }

    private function resellerId(Request $request): int
    {
        $id = (int) $request->user()->reseller_id;
        abort_if($id < 1, 403);
        return $id;
    }

    private function owned(Request $request, string $id): MasterSubscription
    {
        return MasterSubscription::query()->where('reseller_id', $this->resellerId($request))->findOrFail($id);
    }
}
