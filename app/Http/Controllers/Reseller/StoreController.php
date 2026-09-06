<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\Request;

final class StoreController extends Controller
{
    public function index(Request $request)
    {
        $resellerId = $this->resellerId($request);
        $stores = Store::query()->where('reseller_id', $resellerId)->withCount('subscriptions')->orderBy('id')->paginate(20)->withQueryString();
        return view('reseller.stores.index', compact('stores'));
    }

    public function create(Request $request)
    {
        $this->resellerId($request);
        return view('reseller.stores.form', ['store' => null]);
    }

    public function store(Request $request, StoreService $service)
    {
        $resellerId = $this->resellerId($request);
        $data = $this->validated($request);
        $service->create($resellerId, $data['name'], $data['brand_color'], $request->file('logo'));
        return redirect()->route('reseller.stores.index')->with('success', 'فروشگاه ایجاد شد.');
    }

    public function edit(Request $request, int $store)
    {
        $model = $this->owned($request, $store);
        return view('reseller.stores.form', ['store' => $model]);
    }

    public function update(Request $request, int $store, StoreService $service)
    {
        $model = $this->owned($request, $store);
        $data = $this->validated($request);
        $service->update($model, $data['name'], $data['brand_color'], $request->file('logo'));
        return redirect()->route('reseller.stores.index')->with('success', 'فروشگاه بروزرسانی شد.');
    }

    public function destroy(Request $request, int $store)
    {
        $model = $this->owned($request, $store);
        $model->delete();
        return redirect()->route('reseller.stores.index')->with('success', 'فروشگاه آرشیو شد؛ سوابق آن حفظ شده‌اند.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required','string','max:120'],
            'brand_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable','file','max:2048','mimes:png,jpg,jpeg,webp'],
        ]);
    }

    private function resellerId(Request $request): int
    {
        $id = (int) $request->user()->reseller_id;
        abort_if($id < 1, 403);
        return $id;
    }

    private function owned(Request $request, int $store): Store
    {
        return Store::query()->where('reseller_id', $this->resellerId($request))->findOrFail($store);
    }
}
