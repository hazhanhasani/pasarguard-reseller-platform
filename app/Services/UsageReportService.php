<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UsageReportService
{
    /** @return array{query:Builder,columns:array<string,string>} */
    public function build(?int $resellerId, string $dimension, ?string $from, ?string $to): array
    {
        $allowed = $resellerId === null
            ? ['date','reseller','store','subscription','provider']
            : ['date','store','subscription'];
        if (!in_array($dimension, $allowed, true)) throw new InvalidArgumentException('invalid_report_dimension');

        $query = DB::table('usage_charges as uc')
            ->join('master_subscriptions as ms', 'ms.master_subscription_id', '=', 'uc.master_subscription_id')
            ->join('stores as st', 'st.id', '=', 'ms.store_id')
            ->join('resellers as r', 'r.id', '=', 'uc.reseller_id')
            ->join('usage_snapshots as us', 'us.id', '=', 'uc.usage_snapshot_id')
            ->join('providers as p', 'p.id', '=', 'us.provider_id');

        if ($resellerId !== null) $query->where('uc.reseller_id', $resellerId);
        if ($from !== null) $query->where('uc.created_at', '>=', $from.' 00:00:00');
        if ($to !== null) {
            $exclusive = Carbon::createFromFormat('Y-m-d', $to)->addDay()->format('Y-m-d').' 00:00:00';
            $query->where('uc.created_at', '<', $exclusive);
        }

        [$select, $group, $columns] = match ($dimension) {
            'date' => [
                [DB::raw('DATE(uc.created_at) as report_key'), DB::raw('DATE(uc.created_at) as label')],
                [DB::raw('DATE(uc.created_at)')], ['label' => 'تاریخ'],
            ],
            'reseller' => [['r.id as report_key','r.name as label'], ['r.id','r.name'], ['label' => 'نماینده']],
            'store' => [['st.id as report_key','st.name as label'], ['st.id','st.name'], ['label' => 'فروشگاه']],
            'subscription' => [['ms.master_subscription_id as report_key','ms.name as label'], ['ms.master_subscription_id','ms.name'], ['label' => 'اشتراک']],
            'provider' => [['p.id as report_key','p.name as label'], ['p.id','p.name'], ['label' => 'Provider']],
        };

        $query->select($select)
            ->addSelect([
                DB::raw('SUM(uc.usage_bytes) as usage_bytes'),
                DB::raw('SUM(uc.charged_minor) as cost_minor'),
                DB::raw('COUNT(*) as sample_count'),
            ])
            ->groupBy($group)
            ->orderByDesc('usage_bytes');

        $columns += [
            'usage_bytes' => 'مصرف (Bytes)',
            'cost_minor' => 'هزینه (ریال)',
            'sample_count' => 'نمونه‌ها',
        ];
        return ['query' => $query, 'columns' => $columns];
    }
}
