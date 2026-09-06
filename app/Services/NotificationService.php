<?php
namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final class NotificationService
{
    public function signal(
        string $eventKey,
        string $entityKey,
        string $severity,
        string $title,
        ?string $message = null,
        string $audience = 'super_admin',
        ?int $resellerId = null,
    ): Notification {
        $dedup = hash('sha256', implode('|', [$eventKey,$entityKey,$audience,$resellerId ?? 0]));
        return DB::transaction(function () use ($dedup,$eventKey,$severity,$title,$message,$audience,$resellerId) {
            $notification = Notification::query()->where('dedup_key', $dedup)->lockForUpdate()->first();
            if (!$notification) {
                return Notification::query()->create([
                    'dedup_key' => $dedup,
                    'event_key' => $eventKey,
                    'audience_type' => $audience,
                    'reseller_id' => $resellerId,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'occurrence_count' => 1,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]);
            }
            $notification->severity = $severity;
            $notification->title = $title;
            $notification->message = $message;
            $notification->occurrence_count = (int) $notification->occurrence_count + 1;
            $notification->last_seen_at = now();
            $notification->resolved_at = null;
            $notification->save();
            return $notification;
        }, 5);
    }

    public function resolve(string $eventKey, string $entityKey, string $audience = 'super_admin', ?int $resellerId = null): void
    {
        $dedup = hash('sha256', implode('|', [$eventKey,$entityKey,$audience,$resellerId ?? 0]));
        Notification::query()->where('dedup_key', $dedup)->whereNull('resolved_at')->update([
            'resolved_at' => now(), 'updated_at' => now(),
        ]);
    }
}
