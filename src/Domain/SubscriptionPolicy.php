<?php
declare(strict_types=1);
namespace ResellerPlatform\Domain;

final class SubscriptionPolicy
{
    /** Independent blocking facts avoid recharging erasing a manual suspension. */
    public static function desiredState(bool $deleted, bool $manualSuspended, ?int $expiresAt,
        int $now, int $quotaBytes, int $usedBytes, int $walletBalance): string
    {
        if ($quotaBytes < 0 || $usedBytes < 0) throw new \InvalidArgumentException('Negative quota or usage');
        return match (true) {
            $deleted => 'deleted',
            $manualSuspended => 'manual_suspended',
            $expiresAt !== null && $expiresAt <= $now => 'expired',
            $quotaBytes !== 0 && $usedBytes >= $quotaBytes => 'quota_exceeded',
            $walletBalance <= 0 => 'wallet_zero',
            default => 'active',
        };
    }
}
