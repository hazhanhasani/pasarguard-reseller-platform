<?php
namespace App\Payments;

use RuntimeException;

final class BluPalException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?int $status = null)
    {
        parent::__construct($reason, $status ?? 0);
    }
}
