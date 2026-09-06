<?php
namespace App\Services;

use Illuminate\Foundation\Application;
use RuntimeException;
use ZipArchive;

final class DiagnosticBundleService
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function create(): string
    {
        $dir = storage_path('app/diagnostics');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('diagnostic_directory_failed');
        @chmod($dir, 0700);
        $path = $dir.'/diagnostic-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(12)).'.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('diagnostic_zip_open_failed');
        try {
            $health = $this->redact($this->health->collect());
            $zip->addFromString('system-health.json', json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $zip->addFromString('runtime.json', json_encode([
                'generated_at'=>now()->toIso8601String(),
                'laravel_version'=>Application::VERSION,
                'php_version'=>PHP_VERSION,
                'app_env'=>(string) app()->environment(),
                'timezone'=>(string) config('app.timezone'),
                'debug'=>(bool) config('app.debug'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $zip->addFromString('README.txt', "PasarGuard Reseller Platform diagnostic bundle\nNo raw logs, passwords, API keys, provider credentials or subscription tokens are included.\n");
        } finally { $zip->close(); }
        @chmod($path, 0600);
        if (!is_file($path) || filesize($path) === false) throw new RuntimeException('diagnostic_create_failed');
        return $path;
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/(?:password|passwd|secret|credential|authorization|api[_-]?key|private[_-]?key|access[_-]?token|refresh[_-]?token)/i', $key)) return '[REDACTED]';
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k=>$v) $out[$k] = $this->redact($v, is_string($k) ? $k : null);
            return $out;
        }
        if (is_object($value)) return $this->redact((array)$value, $key);
        return $value;
    }
}
