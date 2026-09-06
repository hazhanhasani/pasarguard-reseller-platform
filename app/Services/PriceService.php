<?php
namespace App\Services;

use App\Models\PriceRate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PriceService
{
    public function setGlobalRate(int $priceMinorPerGb, ?DateTimeInterface $effectiveFrom = null): PriceRate
    {
        if ($priceMinorPerGb < 0) throw new InvalidArgumentException('Price cannot be negative');
        $from = CarbonImmutable::instance($effectiveFrom ?? now())->startOfSecond();

        return DB::transaction(function () use ($priceMinorPerGb, $from) {
            $future = PriceRate::query()->where('effective_from', '>=', $from)->lockForUpdate()->exists();
            if ($future) throw new LogicException('Cannot insert a rate before or at an existing rate boundary');

            $current = PriceRate::query()->whereNull('effective_to')->orderByDesc('effective_from')->lockForUpdate()->first();
            if ($current) {
                if ($current->effective_from->greaterThanOrEqualTo($from)) throw new LogicException('Rate boundary must move forward');
                $current->effective_to = $from;
                $current->save();
            }

            return PriceRate::query()->create([
                'price_minor_per_gb' => $priceMinorPerGb,
                'effective_from' => $from,
                'effective_to' => null,
                'created_at' => now(),
            ]);
        }, 5);
    }

    public function rateAt(DateTimeInterface $at): int
    {
        $row = PriceRate::query()
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) { $q->whereNull('effective_to')->orWhere('effective_to', '>', $at); })
            ->orderByDesc('effective_from')
            ->first();
        if (!$row) throw new LogicException('No global price is effective for this usage timestamp');
        return (int) $row->price_minor_per_gb;
    }
}
