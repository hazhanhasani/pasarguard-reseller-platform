<?php
namespace App\Services;

use InvalidArgumentException;
use OverflowException;

final class UnitParser
{
    public static function decimalGbToBytes(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,9}))?$/D', $value, $m)) throw new InvalidArgumentException('invalid_gb_value');
        $whole = ltrim($m[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 9) throw new OverflowException('volume_too_large');
        $wholeInt = (int) $whole;
        if ($wholeInt > intdiv(PHP_INT_MAX, 1_000_000_000)) throw new OverflowException('volume_too_large');
        $fraction = isset($m[2]) ? (int) str_pad($m[2], 9, '0') : 0;
        return $wholeInt * 1_000_000_000 + $fraction;
    }

    public static function daysToSeconds(int $days): int
    {
        if ($days < 0) throw new InvalidArgumentException('negative_duration');
        if ($days > intdiv(PHP_INT_MAX, 86400)) throw new OverflowException('duration_too_large');
        return $days * 86400;
    }
}
