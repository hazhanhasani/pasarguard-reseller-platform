<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\ProviderManagementService;
use App\Services\ProviderProbeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = Provider::query()->withCount(['mappings','operations']);
        if (($q = trim((string) $request->query('q'))) !== '') {
            $query->where('name', 'like', '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%');
        }
        if (in_array($request->query('health'), ['healthy','slow','degraded','offline','auth_error','maintenance','unknown'], true)) {
            $query->where('health', $request->query('health'));
        }
        return view('admin.providers.index', ['providers' => $query->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.providers.form', ['provider' => null]);
    }

    public function store(Request $request, ProviderManagementService $management, ProviderProbeService $probe)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'api_url' => ['required','url','starts_with:https://','max:500'],
            'api_key' => ['required','string','min:8','max:1024'],
            'group_ids' => ['required','string','max:1000'],
        ]);
        try { $groups = $management->parseGroups($data['group_ids']); }
        catch (\Throwable) { throw ValidationException::withMessages(['group_ids' => 'شناسه گروه‌ها معتبر نیست.']); }

        $provider = $management->create($data['name'], $data['api_url'], $data['api_key'], $groups);
        $provider = $probe->probe($provider);
        return redirect()->route('admin.providers.index')->with(
            $provider->ready ? 'success' : 'error',
            $provider->ready ? 'Provider اضافه شد و Test Connection موفق بود؛ اکنون می‌توانید آن را فعال کنید.' : 'Provider ذخیره شد اما Test Connection ناموفق بود؛ غیرفعال باقی می‌ماند.'
        );
    }

    public function edit(Provider $provider)
    {
        return view('admin.providers.form', compact('provider'));
    }

    public function update(Request $request, Provider $provider, ProviderManagementService $management)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'api_url' => ['required','url','starts_with:https://','max:500'],
            'api_key' => ['nullable','string','min:8','max:1024'],
            'group_ids' => ['required','string','max:1000'],
        ]);
        try { $groups = $management->parseGroups($data['group_ids']); }
        catch (\Throwable) { throw ValidationException::withMessages(['group_ids' => 'شناسه گروه‌ها معتبر نیست.']); }
        $management->update($provider, $data['name'], $data['api_url'], $data['api_key'] ?? null, $groups);
        return redirect()->route('admin.providers.index')->with('success', 'Provider بروزرسانی شد. اگر اطلاعات اتصال تغییر کرده باشد باید دوباره Test Connection اجرا شود.');
    }

    public function test(Provider $provider, ProviderProbeService $probe)
    {
        $provider = $probe->probe($provider);
        return back()->with($provider->ready ? 'success' : 'error', $provider->ready ? 'Test Connection موفق بود.' : 'Test Connection ناموفق بود: '.($provider->last_error ?: 'unknown'));
    }

    public function mode(Request $request, Provider $provider, ProviderManagementService $management)
    {
        $data = $request->validate(['mode' => ['required','in:active,maintenance,disabled']]);
        try { $management->setMode($provider, $data['mode']); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['mode' => $e->getMessage() === 'provider_not_ready' ? 'Provider قبل از فعال‌سازی باید Test Connection موفق داشته باشد.' : 'حالت معتبر نیست.']); }
        return back()->with('success', 'حالت Provider تغییر کرد.');
    }

    public function forceSync(Provider $provider, ProviderManagementService $management)
    {
        $count = $management->forceSync($provider);
        return back()->with('success', number_format($count).' کار Sync/Reconcile در صف قرار گرفت.');
    }

    public function destroy(Provider $provider)
    {
        $provider->mode = 'disabled';
        $provider->save();
        $provider->delete();
        return redirect()->route('admin.providers.index')->with('success', 'Provider آرشیو شد؛ Mapping و سوابق حذف نشده‌اند.');
    }
}
