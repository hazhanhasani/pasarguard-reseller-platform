<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\BackupRestoreService;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backups.index', [
            'backups' => Backup::query()->latest('id')->paginate(25),
            'keepLast' => (int) (DB::table('settings')->where('key', 'backup_keep_last')->value('value') ?? config('platform.backup.keep_last', 10)),
            'maxBytes' => (int) (DB::table('settings')->where('key', 'backup_max_bytes')->value('value') ?? config('platform.backup.max_bytes', 10_737_418_240)),
            'freeBytes' => (int) (disk_free_space(storage_path()) ?: 0),
        ]);
    }

    public function create(Request $request, BackupService $backups)
    {
        $data = $request->validate(['type' => ['required','in:database,persistent,full']]);
        try {
            $backup = $backups->create($data['type'], (int) $request->user()->id, ['source'=>'manual']);
            return back()->with('success', 'Backup #'.$backup->id.' با موفقیت ساخته شد.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'ساخت Backup ناموفق بود. جزئیات امن در Log ثبت شد.');
        }
    }

    public function download(Backup $backup, BackupService $backups)
    {
        $path = $backups->pathFor($backup);
        return response()->download($path, 'platform-backup-'.$backup->id.'.zip', [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Backup $backup, BackupService $backups)
    {
        try { $backups->delete($backup); }
        catch (\Throwable $e) { report($e); return back()->with('error', 'حذف Backup ناموفق بود.'); }
        return back()->with('success', 'Backup حذف شد.');
    }

    public function restore(Request $request, Backup $backup, BackupRestoreService $restore)
    {
        $data = $request->validate([
            'password' => ['required','string','max:1024'],
            'confirmation' => ['required','in:RESTORE'],
        ]);
        if (!Hash::check($data['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'رمز عبور صحیح نیست.']);
        }
        try {
            $restore->restore($backup, (int) $request->user()->id);
            return redirect()->route('admin.backups.index')->with('success', 'Restore کامل شد و Safety Backup قبل از عملیات ایجاد شد.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.backups.index')->with('error', 'Restore ناموفق بود؛ بازیابی Safety Backup در صورت امکان اجرا شد. Log سیستم را بررسی کنید.');
        }
    }

    public function retention(Request $request, BackupService $backups)
    {
        $data = $request->validate([
            'keep_last' => ['required','integer','min:1','max:500'],
            'max_gb' => ['required','numeric','min:0','max:100000'],
        ]);
        $maxBytes = (int) round((float) $data['max_gb'] * 1_000_000_000);
        $backups->updateRetention((int) $data['keep_last'], $maxBytes);
        return back()->with('success', 'Backup retention بروزرسانی شد.');
    }
}
