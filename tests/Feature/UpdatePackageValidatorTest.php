<?php
namespace Tests\Feature;

use App\Services\UpdatePackageValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class UpdatePackageValidatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('settings')->updateOrInsert(['key'=>'app_version'], ['value'=>'0.10.0','updated_at'=>now()]);
    }

    public function test_valid_safe_forward_package_is_accepted(): void
    {
        $path = $this->package([
            'application/app/Support/ReleaseMarker.php' => "<?php\nreturn 'ok';\n",
            'changelog.md' => "# 0.11.0\nSafe update\n",
        ]);
        try {
            $result = app(UpdatePackageValidator::class)->validate($path);
            $this->assertSame('0.11.0', $result['manifest']['version']);
            $this->assertContains('application/app/Support/ReleaseMarker.php', $result['files']);
        } finally { @unlink($path); }
    }

    public function test_package_cannot_overwrite_env(): void
    {
        $path = $this->package(['application/.env' => "APP_KEY=stolen\n"]);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('update_protected_path');
            app(UpdatePackageValidator::class)->validate($path);
        } finally { @unlink($path); }
    }

    public function test_checksum_tampering_is_rejected(): void
    {
        $path = $this->package(['application/app/Support/Tamper.php' => "<?php return 1;\n"], ['force_bad_checksum'=>true]);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('update_checksum_mismatch');
            app(UpdatePackageValidator::class)->validate($path);
        } finally { @unlink($path); }
    }

    public function test_destructive_migration_is_rejected(): void
    {
        $path = $this->package([
            'migrations/2026_09_07_000001_bad.php' => "<?php\nuse Illuminate\\Support\\Facades\\Schema;\nSchema::dropIfExists('wallet_ledger');\n",
        ]);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('update_migration_not_safe_forward');
            app(UpdatePackageValidator::class)->validate($path);
        } finally { @unlink($path); }
    }

    public function test_path_traversal_is_rejected(): void
    {
        $path = $this->package(['../evil.php' => "<?php return 'evil';\n"]);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('update_package_path_invalid');
            app(UpdatePackageValidator::class)->validate($path);
        } finally { @unlink($path); }
    }

    /** @param array<string,string> $files */
    private function package(array $files, array $options = []): string
    {
        $path = storage_path('framework/update-test-'.bin2hex(random_bytes(10)).'.zip');
        $checksums = [];
        foreach ($files as $name => $content) $checksums[$name] = hash('sha256', $content);
        if (($options['force_bad_checksum'] ?? false) && $checksums !== []) {
            $key = array_key_first($checksums);
            $checksums[$key] = str_repeat('0', 64);
        }
        $manifest = [
            'version' => '0.11.0',
            'minimum_version' => '0.10.0',
            'php_requirement' => '>=8.3',
            'database_requirement' => 'mysql>=8.0|mariadb>=10.3',
            'checksums' => $checksums,
            'migration_version' => 7,
            'release_date' => '2026-09-06T16:00:00Z',
            'release_channel' => 'stable',
        ];
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) $this->fail('Could not create test ZIP');
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        foreach ($files as $name => $content) $zip->addFromString($name, $content);
        $zip->close();
        return $path;
    }
}
