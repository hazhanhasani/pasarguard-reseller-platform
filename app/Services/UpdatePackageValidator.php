<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class UpdatePackageValidator
{
    /** @return array{manifest:array,files:array<int,string>} */
    public function validate(string $path): array
    {
        if (!is_file($path)) throw new InvalidArgumentException('update_package_missing');
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > (int) config('platform.update.max_package_bytes', 134_217_728)) throw new InvalidArgumentException('update_package_size_invalid');

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new InvalidArgumentException('update_package_invalid_zip');
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > (int) config('platform.update.max_entries', 10_000)) throw new InvalidArgumentException('update_package_entry_count_invalid');
            $files = [];
            $seen = [];
            $totalUncompressed = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) throw new InvalidArgumentException('update_package_stat_failed');
                $name = $this->normalize((string) ($stat['name'] ?? ''));
                $lower = strtolower($name);
                if (isset($seen[$lower])) throw new InvalidArgumentException('update_package_duplicate_path');
                $seen[$lower] = true;
                $totalUncompressed += (int) ($stat['size'] ?? 0);
                if ($totalUncompressed > (int) config('platform.update.max_uncompressed_bytes', 536_870_912)) throw new InvalidArgumentException('update_package_uncompressed_too_large');
                if ((int) ($stat['size'] ?? 0) > (int) config('platform.update.max_entry_bytes', 67_108_864)) throw new InvalidArgumentException('update_package_entry_too_large');
                $opsys = 0; $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && (($attr >> 16) & 0170000) === 0120000) throw new InvalidArgumentException('update_package_symlink_rejected');
                if (!$this->allowedTopLevel($name)) throw new InvalidArgumentException('update_package_unknown_root');
                if (!str_ends_with($name, '/')) $files[] = $name;
            }

            if (!in_array('manifest.json', $files, true)) throw new InvalidArgumentException('update_manifest_missing');
            $manifestRaw = $zip->getFromName('manifest.json');
            if (!is_string($manifestRaw) || strlen($manifestRaw) > 1_048_576) throw new InvalidArgumentException('update_manifest_invalid');
            try { $manifest = json_decode($manifestRaw, true, 64, JSON_THROW_ON_ERROR); }
            catch (\Throwable) { throw new InvalidArgumentException('update_manifest_invalid_json'); }
            if (!is_array($manifest)) throw new InvalidArgumentException('update_manifest_invalid');
            $this->validateManifest($manifest);

            $checksums = $manifest['checksums'];
            if (!is_array($checksums)) throw new InvalidArgumentException('update_checksums_invalid');
            $archiveFiles = array_fill_keys($files, true);
            foreach ($files as $file) {
                if ($file === 'manifest.json') continue;
                if ($this->isProtected($file)) throw new InvalidArgumentException('update_protected_path');
                if (!array_key_exists($file, $checksums) || !is_string($checksums[$file]) || !preg_match('/^[a-f0-9]{64}$/iD', $checksums[$file])) throw new InvalidArgumentException('update_checksum_missing');
                $actual = $this->hashEntry($zip, $file);
                if (!hash_equals(strtolower($checksums[$file]), $actual)) throw new InvalidArgumentException('update_checksum_mismatch');
                if (str_starts_with($file, 'migrations/')) $this->validateMigration($zip, $file);
            }
            foreach ($checksums as $file => $hash) {
                if (!is_string($file) || $file === 'manifest.json') throw new InvalidArgumentException('update_checksums_invalid');
                $normalized = $this->normalize($file);
                if (!isset($archiveFiles[$normalized])) throw new InvalidArgumentException('update_checksum_extra_entry');
            }

            return ['manifest' => $manifest, 'files' => $files];
        } finally {
            $zip->close();
        }
    }

    private function validateManifest(array $manifest): void
    {
        foreach (['version','minimum_version','php_requirement','database_requirement','checksums','migration_version','release_date'] as $required) {
            if (!array_key_exists($required, $manifest)) throw new InvalidArgumentException('update_manifest_missing_'.$required);
        }
        $version = (string) $manifest['version'];
        $minimum = (string) $manifest['minimum_version'];
        if (!$this->version($version) || !$this->version($minimum)) throw new InvalidArgumentException('update_version_invalid');
        $current = (string) (DB::table('settings')->where('key', 'app_version')->value('value') ?? config('platform.version', '0.0.0'));
        if (version_compare($current, $minimum, '<')) throw new InvalidArgumentException('update_minimum_version_not_met');
        if (!version_compare($version, $current, '>')) throw new InvalidArgumentException('update_version_not_newer');
        if (!$this->phpCompatible((string) $manifest['php_requirement'])) throw new InvalidArgumentException('update_php_incompatible');
        if (!$this->databaseCompatible((string) $manifest['database_requirement'])) throw new InvalidArgumentException('update_database_incompatible');
        if ((!is_int($manifest['migration_version']) && !ctype_digit((string) $manifest['migration_version'])) || (int) $manifest['migration_version'] < 0) throw new InvalidArgumentException('update_migration_version_invalid');
        try { new \DateTimeImmutable((string) $manifest['release_date']); }
        catch (\Throwable) { throw new InvalidArgumentException('update_release_date_invalid'); }
        if (isset($manifest['release_channel']) && !in_array($manifest['release_channel'], ['stable','beta','rc'], true)) throw new InvalidArgumentException('update_release_channel_invalid');
    }

    private function validateMigration(ZipArchive $zip, string $file): void
    {
        if (!preg_match('#^migrations/[A-Za-z0-9_.-]+\.php$#D', $file)) throw new InvalidArgumentException('update_migration_path_invalid');
        $target = database_path('migrations/'.basename($file));
        if (is_file($target)) throw new InvalidArgumentException('update_migration_overwrite_rejected');
        $code = $zip->getFromName($file);
        if (!is_string($code)) throw new InvalidArgumentException('update_migration_read_failed');
        $dangerous = [
            '/Schema\s*::\s*drop/i', '/dropIfExists\s*\(/i', '/->\s*dropColumn\s*\(/i',
            '/->\s*renameColumn\s*\(/i', '/\bTRUNCATE\b/i', '/\bDROP\s+TABLE\b/i',
            '/\bALTER\s+TABLE\b[^;]*\bDROP\b/i', '/\bDELETE\s+FROM\b/i',
        ];
        foreach ($dangerous as $pattern) if (preg_match($pattern, $code)) throw new InvalidArgumentException('update_migration_not_safe_forward');
    }

    private function normalize(string $path): string
    {
        if (str_contains($path, "\0")) throw new InvalidArgumentException('update_package_path_invalid');
        $path = str_replace('\\', '/', $path);
        while (str_contains($path, '//')) $path = str_replace('//', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) || preg_match('#(^|/)\.\.?(/|$)#', $path)) throw new InvalidArgumentException('update_package_path_invalid');
        return $path;
    }

    private function allowedTopLevel(string $path): bool
    {
        return $path === 'manifest.json' || $path === 'changelog.md' || str_starts_with($path, 'application/') || str_starts_with($path, 'migrations/');
    }

    private function isProtected(string $file): bool
    {
        if (!str_starts_with($file, 'application/')) return false;
        $relative = substr($file, strlen('application/'));
        $exact = ['.env','storage','public/uploads','bootstrap/cache','.git','database/migrations'];
        foreach ($exact as $protected) if ($relative === $protected || str_starts_with($relative, $protected.'/')) return true;
        return false;
    }

    private function hashEntry(ZipArchive $zip, string $file): string
    {
        $stream = $zip->getStream($file);
        if (!is_resource($stream)) throw new InvalidArgumentException('update_entry_read_failed');
        $context = hash_init('sha256');
        try { if (hash_update_stream($context, $stream) === false) throw new InvalidArgumentException('update_entry_hash_failed'); }
        finally { fclose($stream); }
        return hash_final($context);
    }

    private function version(string $value): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $value);
    }

    private function phpCompatible(string $requirement): bool
    {
        $requirement = trim($requirement);
        if (preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/D', $requirement, $m)) {
            $base = $m[1].'.'.$m[2].'.'.($m[3] ?? '0');
            $upper = ((int) $m[1] + 1).'.0.0';
            return version_compare(PHP_VERSION, $base, '>=') && version_compare(PHP_VERSION, $upper, '<');
        }
        if (!preg_match('/^(>=|>|<=|<|=)?\s*(\d+(?:\.\d+){1,2})$/D', $requirement, $m)) return false;
        return version_compare(PHP_VERSION, $m[2], $m[1] ?: '>=');
    }

    private function databaseCompatible(string $requirement): bool
    {
        $row = DB::selectOne('SELECT VERSION() AS version');
        $raw = strtolower((string) ($row->version ?? ''));
        $engine = str_contains($raw, 'mariadb') ? 'mariadb' : 'mysql';
        if (!preg_match('/\d+\.\d+(?:\.\d+)?/', $raw, $v)) return false;
        foreach (explode('|', strtolower($requirement)) as $alternative) {
            $alternative = trim($alternative);
            if (!preg_match('/^(mysql|mariadb)\s*(>=|>|<=|<|=)\s*(\d+(?:\.\d+){1,2})$/D', $alternative, $m)) continue;
            if ($m[1] === $engine && version_compare($v[0], $m[3], $m[2])) return true;
        }
        return false;
    }
}
