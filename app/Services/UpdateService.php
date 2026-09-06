<?php
namespace App\Services;

use App\Models\UpdateHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class UpdateService
{
    public function __construct(
        private readonly UpdatePackageValidator $validator,
        private readonly BackupService $backups,
        private readonly MaintenanceLockService $locks,
    ) {}

    public function upload(UploadedFile $file, ?int $actorId = null): UpdateHistory
    {
        if (!$file->isValid()) throw new InvalidArgumentException('update_upload_invalid');
        $dir = $this->packageDirectory();
        $name = bin2hex(random_bytes(24)).'.zip';
        $original = basename((string) $file->getClientOriginalName());
        $original = preg_replace('/[^\pL\pN._ -]+/u', '_', $original) ?: 'release.zip';
        $original = mb_substr($original, 0, 191);
        $file->move($dir, $name);
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        @chmod($path, 0600);

        try {
            $validated = $this->validator->validate($path);
            $hash = hash_file('sha256', $path);
            if ($hash === false) throw new RuntimeException('update_package_hash_failed');
            $manifest = $validated['manifest'];
            return UpdateHistory::query()->create([
                'version' => (string) $manifest['version'],
                'channel' => (string) ($manifest['release_channel'] ?? 'stable'),
                'package_name' => $original,
                'package_path' => $name,
                'package_sha256' => $hash,
                'status' => 'validated',
                'manifest' => $manifest,
                'created_by' => $actorId,
            ]);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }

    public function apply(UpdateHistory $update, ?int $actorId = null): UpdateHistory
    {
        $path = $this->pathFor($update);
        $validated = $this->validator->validate($path);
        $currentHash = hash_file('sha256', $path);
        if (!is_string($currentHash) || !hash_equals((string) $update->package_sha256, $currentHash)) throw new RuntimeException('update_package_checksum_changed');
        if ((string) $validated['manifest']['version'] !== (string) $update->version) throw new RuntimeException('update_manifest_version_changed');

        $token = $this->locks->acquire('update_apply');
        $workRoot = storage_path('app/update-work/'.$update->id.'-'.bin2hex(random_bytes(10)));
        $staging = $workRoot.'/staging';
        $rollback = $workRoot.'/rollback';
        $map = [];
        $started = false;
        $migrationsStarted = false;
        try {
            $update = DB::transaction(function () use ($update) {
                $locked = UpdateHistory::query()->lockForUpdate()->findOrFail($update->id);
                if ($locked->status !== 'validated') throw new InvalidArgumentException('update_not_ready');
                $locked->status = 'preparing';
                $locked->started_at = now();
                $locked->error = null;
                $locked->rollback_status = null;
                $locked->save();
                return $locked->fresh();
            }, 5);
            $started = true;

            if (!mkdir($staging, 0700, true) || !mkdir($rollback, 0700, true)) throw new RuntimeException('update_work_directory_failed');
            $backup = $this->backups->create('full', $actorId, ['reason'=>'pre_update','update_id'=>$update->id], false);
            $update->backup_id = $backup->id;
            $update->status = 'applying';
            $update->save();

            $this->stage($path, $staging, $validated['files']);
            foreach ($validated['files'] as $archivePath) {
                $targetRelative = $this->targetRelative($archivePath);
                if ($targetRelative === null) continue;
                $source = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archivePath);
                $target = base_path(str_replace('/', DIRECTORY_SEPARATOR, $targetRelative));
                if (!is_file($source)) throw new RuntimeException('update_staged_file_missing');
                if (is_dir($target)) throw new RuntimeException('update_target_is_directory');

                $entry = ['target'=>$targetRelative,'existed'=>is_file($target),'rollback'=>null];
                if ($entry['existed']) {
                    $rollbackFile = $rollback.'/'.hash('sha256', $targetRelative).'.bak';
                    if (!copy($target, $rollbackFile)) throw new RuntimeException('update_rollback_snapshot_failed');
                    @chmod($rollbackFile, 0600);
                    $entry['rollback'] = basename($rollbackFile);
                }
                $map[] = $entry;
                $this->atomicReplace($source, $target);
            }
            file_put_contents($rollback.'/map.json', json_encode($map, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);

            $migrationsStarted = true;
            Artisan::call('migrate', ['--force'=>true]);
            Artisan::call('optimize:clear');
            $this->healthCheck();

            DB::table('settings')->updateOrInsert(['key'=>'app_version'], ['value'=>(string) $update->version, 'updated_at'=>now()]);
            $update = UpdateHistory::query()->findOrFail($update->id);
            $update->status = 'completed';
            $update->rollback_status = 'not_required';
            $update->completed_at = now();
            $update->save();
            return $update->fresh();
        } catch (\Throwable $e) {
            if ($started) {
                $rollbackStatus = 'not_started';
                try {
                    if ($map !== []) {
                        $this->rollbackFiles($map, $rollback, $migrationsStarted);
                        $rollbackStatus = $migrationsStarted ? 'application_files_restored_safe_forward' : 'files_restored';
                    }
                } catch (\Throwable $rollbackError) {
                    report($rollbackError);
                    $rollbackStatus = 'rollback_failed';
                }
                try {
                    $fresh = UpdateHistory::query()->find($update->id);
                    if ($fresh) {
                        $fresh->status = 'failed';
                        $fresh->rollback_status = $rollbackStatus;
                        $fresh->error = $this->safeReason($e);
                        $fresh->completed_at = now();
                        $fresh->save();
                    }
                } catch (\Throwable $historyError) { report($historyError); }
            }
            report($e);
            throw $e;
        } finally {
            $this->removeTree($workRoot);
            $this->locks->release($token);
        }
    }

    public function deletePackage(UpdateHistory $update): void
    {
        if (in_array($update->status, ['preparing','applying'], true)) throw new InvalidArgumentException('update_in_progress');
        $path = $this->packageDirectory().DIRECTORY_SEPARATOR.basename($update->package_path);
        if (is_file($path) && !@unlink($path)) throw new RuntimeException('update_package_delete_failed');
        $update->package_path = '';
        $update->save();
    }

    public function pathFor(UpdateHistory $update): string
    {
        if ($update->package_path === '') throw new RuntimeException('update_package_deleted');
        $base = realpath($this->packageDirectory());
        $candidate = realpath($this->packageDirectory().DIRECTORY_SEPARATOR.basename($update->package_path));
        if ($base === false || $candidate === false || !str_starts_with($candidate, $base.DIRECTORY_SEPARATOR)) throw new RuntimeException('update_package_missing');
        $hash = hash_file('sha256', $candidate);
        if (!is_string($hash) || !is_string($update->package_sha256) || !hash_equals($update->package_sha256, $hash)) throw new RuntimeException('update_package_checksum_changed');
        return $candidate;
    }

    private function stage(string $packagePath, string $staging, array $files): void
    {
        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) throw new RuntimeException('update_package_invalid_zip');
        try {
            foreach ($files as $archivePath) {
                if (!str_starts_with($archivePath, 'application/') && !str_starts_with($archivePath, 'migrations/')) continue;
                $in = $zip->getStream($archivePath);
                if (!is_resource($in)) throw new RuntimeException('update_entry_read_failed');
                $target = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archivePath);
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { fclose($in); throw new RuntimeException('update_stage_directory_failed'); }
                $out = @fopen($target, 'xb');
                if ($out === false) { fclose($in); throw new RuntimeException('update_stage_file_failed'); }
                try {
                    if (stream_copy_to_stream($in, $out) === false) throw new RuntimeException('update_stage_write_failed');
                } finally { fclose($in); fclose($out); }
            }
        } finally { $zip->close(); }
    }

    private function targetRelative(string $archivePath): ?string
    {
        if (str_starts_with($archivePath, 'application/')) {
            $relative = substr($archivePath, strlen('application/'));
            return $relative === '' ? null : $relative;
        }
        if (str_starts_with($archivePath, 'migrations/')) return 'database/migrations/'.basename($archivePath);
        return null;
    }

    private function atomicReplace(string $source, string $target): void
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('update_target_directory_failed');
        $temp = $dir.'/._update_'.bin2hex(random_bytes(12));
        if (!copy($source, $temp)) throw new RuntimeException('update_target_copy_failed');
        @chmod($temp, 0644);
        if (!@rename($temp, $target)) { @unlink($temp); throw new RuntimeException('update_target_replace_failed'); }
    }

    private function rollbackFiles(array $map, string $rollbackDir, bool $preserveMigrations): void
    {
        foreach (array_reverse($map) as $entry) {
            $relative = (string) ($entry['target'] ?? '');
            if ($relative === '' || str_contains($relative, '..')) throw new RuntimeException('update_rollback_map_invalid');
            if ($preserveMigrations && str_starts_with($relative, 'database/migrations/')) continue;
            $target = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if (($entry['existed'] ?? false) === true) {
                $backup = $rollbackDir.DIRECTORY_SEPARATOR.basename((string) ($entry['rollback'] ?? ''));
                if (!is_file($backup)) throw new RuntimeException('update_rollback_file_missing');
                $this->atomicReplace($backup, $target);
            } elseif (is_file($target) && !@unlink($target)) {
                throw new RuntimeException('update_rollback_delete_failed');
            }
        }
        Artisan::call('optimize:clear');
    }

    private function healthCheck(): void
    {
        $row = DB::selectOne('SELECT 1 AS ok');
        if ((int) ($row->ok ?? 0) !== 1) throw new RuntimeException('update_health_database_failed');
        foreach (['artisan','bootstrap/app.php','routes/web.php'] as $file) if (!is_file(base_path($file))) throw new RuntimeException('update_health_file_failed');
        if (!Route::has('subscription.public') || !Route::has('admin.dashboard')) throw new RuntimeException('update_health_routes_failed');
        if (!is_writable(storage_path()) || !is_writable(base_path('bootstrap/cache'))) throw new RuntimeException('update_health_writable_failed');
    }

    private function packageDirectory(): string
    {
        $dir = storage_path('app/update-packages');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('update_package_directory_failed');
        @chmod($dir, 0700);
        return $dir;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) { if (is_file($path)) @unlink($path); return; }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($path);
    }

    private function safeReason(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (preg_match('/^[a-z0-9_]{3,120}$/D', $message)) return $message;
        return $e::class;
    }
}
