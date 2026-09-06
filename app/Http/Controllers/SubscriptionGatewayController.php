<?php
namespace App\Http\Controllers;

use App\Services\SubscriptionClientDetector;
use App\Services\SubscriptionOutputService;
use App\Services\SubscriptionTokenService;
use Illuminate\Http\Request;

final class SubscriptionGatewayController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        SubscriptionTokenService $tokens,
        SubscriptionClientDetector $detector,
        SubscriptionOutputService $outputs,
    ) {
        $subscription = $tokens->resolve($token);
        abort_if(!$subscription, 404);
        $subscription->load('store');

        if ($detector->wantsJson($request)) {
            if ($subscription->desired_state !== 'active') {
                return response()->json([
                    'error' => 'subscription_inactive',
                    'status' => $subscription->desired_state,
                ], 403, [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            return response()->json($outputs->aggregate($subscription), 200, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Subscription-State' => 'active',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $quota = (int) $subscription->quota_bytes;
        $used = (int) $subscription->used_bytes;
        $remaining = $quota === 0 ? null : max(0, $quota - $used);
        $usagePercent = $quota === 0 ? null : min(100, max(0, (int) floor(($used / max(1, $quota)) * 100)));
        $remainingSeconds = $subscription->expires_at === null ? null : max(0, now()->diffInSeconds($subscription->expires_at, false));
        $duration = (int) $subscription->duration_seconds;
        $timePercent = ($duration === 0 || $remainingSeconds === null)
            ? null
            : min(100, max(0, 100 - (int) floor(($remainingSeconds / max(1, $duration)) * 100)));

        return response()->view('subscription.landing', compact(
            'subscription', 'quota', 'used', 'remaining', 'usagePercent', 'remainingSeconds', 'timePercent'
        ), 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
