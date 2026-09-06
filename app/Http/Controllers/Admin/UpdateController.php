<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateHistory;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UpdateController extends Controller
{
    public function index()
    {
        return view('admin.updates.index', [
            'currentVersion' => (string) (DB::table('settings')->where('key', 'app_version')->value('value') ?? config('platform.version', 'dev')),
            'updates' => UpdateHistory::query()->with('backup')->latest('id')->paginate(20),
            'maxMb' => (int) ceil((int) config('platform.update.max_package_bytes', 134_217_728) / 1_048_576),
        ]);
    }

    public function upload(Request $request, UpdateService $updates)
    {
        $maxKb = max(1, (int) ceil((int) config('platform.update.max_package_bytes', 134_217_728) / 1024));
        $request->validate(['release' => ['required','file','mimes:zip','max:'.$maxKb]]);
        try {
            $update = $updates->upload($request->file('release'), (int) $request->user()->id);
            return back()->with('success', 'بسته نسخه '.$update->version.' اعتبارسنجی شد و آماده Apply است.');
        } catch (\Throwable $e) {
            report($e);
            $reason = preg_match('/^[a-z0-9_]{3,120}$/D', $e->getMessage()) ? $e->getMessage() : $e::class;
            return back()->with('error', 'بسته Update رد شد: '.$reason);
        }
    }

    public function apply(Request $request, UpdateHistory $update, UpdateService $updates)
    {
        $data = $request->validate([
            'password' => ['required','string','max:1024'],
            'confirmation' => ['required','in:APPLY'],
        ]);
        if (!Hash::check($data['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'رمز عبور صحیح نیست.']);
        }
        try {
            $result = $updates->apply($update, (int) $request->user()->id);
            return redirect()->route('admin.updates.index')->with('success', 'نسخه '.$result->version.' با موفقیت نصب شد.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.updates.index')->with('error', 'Update ناموفق بود؛ فایل‌های تغییرکرده در صورت امکان Rollback شدند و Safety Backup حفظ شده است.');
        }
    }

    public function destroyPackage(UpdateHistory $update, UpdateService $updates)
    {
        try { $updates->deletePackage($update); }
        catch (\Throwable $e) { report($e); return back()->with('error', 'حذف فایل بسته ناموفق بود.'); }
        return back()->with('success', 'فایل ZIP این Update حذف شد؛ History باقی ماند.');
    }
}
