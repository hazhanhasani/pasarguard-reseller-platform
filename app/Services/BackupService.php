<?php
namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class BackupService
{
    public function __construct(private readonly MySqlDumpService $database) {}

    public function create(string $type = 'full', ?int $actorId = null, array $metadata = [], bool $enforceRetention = true): Backup
    {
        if (!in_array($type, ['database','persistent','full'], true)) throw new InvalidArgumentException('invalid_backup_type');
        $base = $this->backupDirectory();
        $token = bin2hex(random_bytes(16));
        $relative = 'backup-'.now()->format('Ymd-His').'-'.$token.'.zip';
        $target = $base.DIRECTORY_SEPARATOR.$relative;
        $temp = $base.DIRECTORY_SEPARATOR.'.tmp-'.$token;
        if (!mkdir($temp, 0700, true) && !is_dir($temp)) throw new RuntimeException('backup_temp_directory_failed');

        $backup = Backup::query()->create([
            'type' => $type,
            'status' => 'creating',
            'disk_path' => $relative,
            'app_version' => (string) config('platform.version', 'dev'),
            'migration_level' => DB::table('migrations')->orderByDesc('batch')->orderByDesc('id')->value('migration'),
            'created_by' => $actorId,
            'metadata' => $metadata,
        ]);

        try {
            $manifest = [
                'format' => 'pasarguard-platform-backup-v1',
                'type' => $type,
                'created_at' => now()->toIso8601String(),
                'app_version' => (string) config('platform.version', 'dev'),
                'migration_level' => $backup->migration_level,
                'persistent_roots' => [],
            ];
            if ($type === 'database' || $type === 'full') {
                $this->database->dump($temp.'/database.sql', (array) config('platform.backup.exclude_tables', ['cache','cache_locks','sessions','jobs','failed_jobs']));
                $manifest['database'] = true;
            }

            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('backup_zip_open_failed');
            try {
                if (is_file($temp.'/database.sql') && !$zip->addFile($temp.'/database.sql', 'database.sql')) throw new RuntimeException('backup_zip_database_failed');
                if ($type === 'persistent' || $type === 'full') {
                    foreach ($this->persistentRoots() as $relativeRoot => $absoluteRoot) {
                        if (!is_dir($absoluteRoot)) continue;
                        $archiveRoot = 'persistent/'.trim(str_replace('\\', '/', $relativeRoot), '/');
                        $manifest['persistent_roots'][$archiveRoot] = $relativeRoot;
                        $this->addDirectory($zip, $absoluteRoot, $archiveRoot);
                    }
                }
                $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } finally {
                $zip->close();
            }
            @chmod($target, 0600);
            $size = filesize($target);
            $hash = hash_file('sha256', $target);
            if ($size === false || $hash === false) throw new RuntimeException('backup_integrity_failed');
            $backup->status = 'completed';
            $backup->size_bytes = (int) $size;
            $backup->sha256 = $hash;
            $backup->completed_at = now();
            $backup->save();
            if ($enforceRetention) $this->applyRetention();
            return $backup->fresh();
        } catch (\Throwable $e) {
            if (is_file($target)) @unlink($target);
            $backup->status = 'failed';
            $backup->failure_reason = $this->safeReason($e);
            $backup->completed_at = now();
            $backup->save();
            throw $e;
        } finally {
            $this->removeTree($temp);
        }
    }

    public function pathFor(Backup $backup): string
    {
        $base = realpath($this->backupDirectory());
        $candidate = realpath($this->backupDirectory().DIRECTORY_SEPARATOR.basename($backup->disk_path));
        if ($base === false || $candidate === false || !str_starts_with($candidate, $base.DIRECTORY_SEPARATOR)) throw new RuntimeException('backup_file_missing');
        if ($backup->status !== 'completed' || !is_file($candidate)) throw new RuntimeException('backup_not_ready');
        if (!is_string($backup->sha256) || !hash_equals($backup->sha256, (string) hash_file('sha256', $candidate))) throw new RuntimeException('backup_checksum_mismatch');
        return $candidate;
    }

    public function delete(Backup $backup): void
    {
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.basename($backup->disk_path);
        if (is_file($path) && !@unlink($path)) throw new RuntimeException('backup_delete_failed');
        $backup->delete();
    }

    public function applyRetention(): void
    {
        $keep = max(1, (int) $this->setting('backup_keep_last', (string) config('platform.backup.keep_last', 10)));
        $maxBytes = max(0, (int) $this->setting('backup_max_bytes', (string) config('platform.backup.max_bytes', 10_737_418_240)));
        $backups = Backup::query()->where('status', 'completed')->latest('id')->get();
        $total = 0;
        foreach ($backups as $index => $backup) {
            $total += (int) $backup->size_bytes;
            if ($index < $keep && ($maxBytes === 0 || $total <= $maxBytes)) continue;
            try { $this->delete($backup); } catch (\Throwable) { }
        }
    }

    public function updateRetention(int $keepLast, int $maxBytes): void
    {
        if ($keepLast < 1 || $keepLast > 500 || $maxBytes < 0) throw new InvalidArgumentException('invalid_backup_retention');
        DB::table('settings')->updateOrInsert(['key' => 'backup_keep_last'], ['value' => (string) $keepLast, 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'backup_max_bytes'], ['value' => (string) $maxBytes, 'updated_at' => now()]);
        $this->applyRetention();
    }

    private function backupDirectory(): string
    {
        $dir = storage_path('app/platform-backups');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('backup_directory_failed');
        @chmod($dir, 0700);
        return $dir;
    }

    private function persistentRoots(): array
    {
        $roots = [];
        foreach ((array) config('platform.backup.persistent_paths', ['public/uploads']) as $relative) {
            $relative = trim(str_replace('\\', '/', (string) $relative), '/');
            if ($relative === '' || str_contains($relative, '..')) continue;
            $roots[$relative] = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        }
        return $roots;
    }

    private function addDirectory(ZipArchive $zip, string $root, string $archiveRoot): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) continue;
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            if (!$zip->addFile($file->getPathname(), $archiveRoot.'/'.$relative)) throw new RuntimeException('backup_zip_file_failed');
        }
    }

    private function setting(string $key, string $fallback): string
    {
        return (string) (DB::table('settings')->where('key', $key)->value('value') ?? $fallback);
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
        $allowed = ['backup_temp_directory_failed','backup_zip_open_failed','backup_zip_database_failed','backup_integrity_failed','backup_dump_open_failed','backup_create_table_failed','backup_create_table_missing','backup_table_read_failed'];
        return in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : $e::class;
    }
}
