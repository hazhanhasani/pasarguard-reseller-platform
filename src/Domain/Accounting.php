<?php
declare(strict_types=1);
namespace ResellerPlatform\Domain;

/** Pure calculations. Persistence must lock wallet and mapping in one transaction. */
final class Accounting
{
    public const BYTES_PER_GB = 1_000_000_000;

    /** Carry fractional minor units without floats or per-sample rounding loss.
     * @return array{charge:int,remainder:int}
     */
    public static function charge(int $bytes, int $rate, int $remainder = 0): array
    {
        if ($bytes < 0 || $rate < 0 || $remainder < 0 || $remainder >= self::BYTES_PER_GB) {
            throw new \InvalidArgumentException('Invalid billing input');
        }
        $whole = intdiv($bytes, self::BYTES_PER_GB);
        $part = $bytes % self::BYTES_PER_GB;
        $wholeCost = self::multiply($whole, $rate);
        $numerator = self::add(self::multiply($part, $rate), $remainder);
        return ['charge' => self::add($wholeCost, intdiv($numerator, self::BYTES_PER_GB)),
            'remainder' => $numerator % self::BYTES_PER_GB];
    }

    /** A lower counter without a verified epoch change is ambiguous, not zero usage.
     * Epoch changes require an explicit baseline, established by the adapter/import.
     */
    public static function delta(int $previous, int $current, string $oldEpoch, string $newEpoch, ?int $baseline = null): int
    {
        if ($previous < 0 || $current < 0) throw new \InvalidArgumentException('Negative counter');
        if ($oldEpoch === $newEpoch) {
            if ($current < $previous) throw new \DomainException('Counter regression: reconciliation required');
            return $current - $previous;
        }
        if ($baseline === null || $baseline < 0 || $baseline > $current) {
            throw new \DomainException('Verified epoch baseline required');
        }
        return $current - $baseline;
    }

    private static function multiply(int $a, int $b): int
    {
        if ($b !== 0 && $a > intdiv(PHP_INT_MAX, $b)) throw new \OverflowException('Use decimal arithmetic for this amount');
        return $a * $b;
    }
    private static function add(int $a, int $b): int
    {
        if ($a > PHP_INT_MAX - $b) throw new \OverflowException('Integer overflow');
        return $a + $b;
    }
}
