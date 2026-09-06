<?php
namespace Tests\Feature;

use App\Services\MaintenanceLockService;
use RuntimeException;
use Tests\TestCase;

class MaintenanceLockTest extends TestCase
{
    public function test_lock_is_exclusive_and_owner_can_release_it(): void
    {
        $locks = app(MaintenanceLockService::class);
        $path = storage_path('framework/platform-write.lock');
        if (is_file($path)) @unlink($path);
        $token = $locks->acquire('test');
        try {
            $this->assertTrue($locks->isLocked());
            $this->assertSame('test', $locks->reason());
            try {
                $locks->acquire('second');
                $this->fail('Second maintenance lock must not be acquired.');
            } catch (RuntimeException $e) {
                $this->assertSame('maintenance_lock_busy', $e->getMessage());
            }
        } finally {
            if ($locks->isLocked()) $locks->release($token);
        }
        $this->assertFalse($locks->isLocked());
    }
}
