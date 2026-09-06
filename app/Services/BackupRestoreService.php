<?php
namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

final class BackupRestoreService
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly MaintenanceLockService $locks,
    ) {}

    public function restore(Backup $backup, ?int $actorId = null): void
    {
        $sourcePath = $this->backups->pathFor($backup);
        $token = $this->locks->acquire('backup_restore');
        $safetyPath = null;
        try {
            $safety = $this->backups->create('full', $actorId, ['reason'=>'pre_restore','source_backup_id'=>$backup->id], false);
            $safetyPath = $this->backups->pathFor($safety);
            try {
                $this->restoreArchive($sourcePath);
            } catch (\Throwable $restoreError) {
                if ($safetyPath !== null) {
                    try { $this->restoreArchive($safetyPath); }
                    catch (\Throwable $safetyError) { throw new RuntimeException('restore_failed_and_safety_restore_failed', 0, $safetyError); }
                }
                throw $restoreError;
            }
        } finally {
            $this->locks->release($token);
        }
    }

    private function restoreArchive(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('backup_zip_invalid');
        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
            if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'pasarguard-platform-backup-v1') throw new RuntimeException('backup_manifest_invalid');
            $this->validateEntries($zip);
            if (($manifest['database'] ?? false) === true) $this->restoreDatabase($zip);
            $roots = $manifest['persistent_roots'] ?? [];
            if (is_array($roots) && $roots !== []) $this->restorePersistent($zip, $roots);
        } finally {
            $zip->close();
        }
    }

    private function restoreDatabase(ZipArchive $zip): void
    {
        $stream = $zip->getStream('database.sql');
        if (!is_resource($stream)) throw new RuntimeException('backup_database_missing');
        try {
            while (($line = fgets($stream)) !== false) {
                $sql = trim($line);
                if ($sql === '' || str_starts_with($sql, '--')) continue;
                DB::unprepared($sql);
            }
        } finally {
            fclose($stream);
        }
        DB::purge();
        DB::reconnect();
    }

    /** @param array<string,string> $roots */
    private function restorePersistent(ZipArchive $zip, array $roots): void
    {
        $approved = [];
        foreach ((array) config('platform.backup.persistent_paths', ['public/uploads']) as $relative) {
            $relative = trim(str_replace('\\', '/', (string) $relative), '/');
            if ($relative !== '' && !str_contains($relative, '..')) $approved[$relative] = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        }

        foreach ($roots as $archiveRoot => $relativeRoot) {
            $archiveRoot = trim(str_replace('\\', '/', (string) $archiveRoot), '/');
            $relativeRoot = trim(str_replace('\\', '/', (string) $relativeRoot), '/');
            if (!isset($approved[$relativeRoot]) || !str_starts_with($archiveRoot, 'persistent/')) throw new RuntimeException('backup_persistent_root_invalid');
            $this->clearDirectory($approved[$relativeRoot]);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/') || !str_starts_with($name, 'persistent/')) continue;
            $matched = null;
            foreach ($roots as $archiveRoot => $relativeRoot) {
                $prefix = rtrim((string) $archiveRoot, '/').'/';
                if (str_starts_with($name, $prefix)) { $matched = [$prefix, (string) $relativeRoot]; break; }
            }
            if ($matched === null) throw new RuntimeException('backup_persistent_entry_unmapped');
            [$prefix, $relativeRoot] = $matched;
            $suffix = substr($name, strlen($prefix));
            if ($suffix === '' || str_contains($suffix, '..') || str_starts_with($suffix, '/')) throw new RuntimeException('backup_entry_unsafe');
            $root = $approved[$relativeRoot] ?? null;
            if ($root === null) throw new RuntimeException('backup_persistent_root_invalid');
            $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $suffix);
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('restore_directory_failed');
            $in = $zip->getStream($name);
            $out = @fopen($target, 'wb');
            if (!is_resource($in) || $out === false) {
                if (is_resource($in)) fclose($in);
                if (is_resource($out)) fclose($out);
                throw new RuntimeException('restore_file_open_failed');
            }
            try {
                if (stream_copy_to_stream($in, $out) === false) throw new RuntimeException('restore_file_write_failed');
            } finally {
                fclose($in); fclose($out);
            }
            @chmod($target, 0644);
        }
    }

    private function validateEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);
            if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $normalized)) throw new RuntimeException('backup_entry_unsafe');
            $opsys = 0; $attr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && (($attr >> 16) & 0170000) === 0120000) throw new RuntimeException('backup_symlink_rejected');
        }
    }

    private function clearDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) throw new RuntimeException('restore_directory_failed');
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isLink() || $item->isFile()) { if (!@unlink($item->getPathname())) throw new RuntimeException('restore_cleanup_failed'); }
            elseif (!@rmdir($item->getPathname())) throw new RuntimeException('restore_cleanup_failed');
        }
    }
}
