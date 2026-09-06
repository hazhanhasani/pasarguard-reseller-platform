<?php
namespace App\Services;

use RuntimeException;

final class MaintenanceLockService
{
    public function acquire(string $reason): string
    {
        $path = $this->path();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('maintenance_lock_directory_failed');
        $handle = @fopen($path, 'x');
        if ($handle === false) throw new RuntimeException('maintenance_lock_busy');
        $token = bin2hex(random_bytes(32));
        try {
            fwrite($handle, json_encode(['token'=>$token,'reason'=>$reason,'created_at'=>gmdate('c')], JSON_THROW_ON_ERROR));
        } finally {
            fclose($handle);
        }
        @chmod($path, 0600);
        return $token;
    }

    public function release(string $token): void
    {
        $path = $this->path();
        if (!is_file($path)) return;
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !isset($data['token']) || !hash_equals((string) $data['token'], $token)) throw new RuntimeException('maintenance_lock_owner_mismatch');
        if (!@unlink($path) && is_file($path)) throw new RuntimeException('maintenance_lock_release_failed');
    }

    public function isLocked(): bool { return is_file($this->path()); }

    public function reason(): ?string
    {
        $raw = @file_get_contents($this->path());
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) && isset($data['reason']) ? (string) $data['reason'] : null;
    }

    private function path(): string { return storage_path('framework/platform-write.lock'); }
}
