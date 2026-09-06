<?php
namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_backup_is_created_outside_public_with_manifest_and_checksum(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create('database', null, ['source'=>'test'], false);
        $path = $service->pathFor($backup);
        try {
            $this->assertStringStartsWith(realpath(storage_path('app/platform-backups')).DIRECTORY_SEPARATOR, realpath($path));
            $this->assertSame(hash_file('sha256', $path), $backup->sha256);
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            $this->assertSame('pasarguard-platform-backup-v1', $manifest['format'] ?? null);
            $this->assertSame('database', $manifest['type'] ?? null);
            $this->assertNotFalse($zip->locateName('database.sql'));
            $zip->close();
        } finally {
            $service->delete($backup);
        }
    }

    public function test_checksum_tampering_is_rejected(): void
    {
        $service = app(BackupService::class);
        $backup = $service->create('database', null, ['source'=>'test'], false);
        $path = $service->pathFor($backup);
        file_put_contents($path, 'tamper', FILE_APPEND);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('backup_checksum_mismatch');
            $service->pathFor($backup);
        } finally {
            if (is_file($path)) @unlink($path);
            $backup->delete();
        }
    }
}
