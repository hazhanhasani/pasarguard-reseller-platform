<?php
namespace App\Services;

use Illuminate\Http\Request;

final class SubscriptionClientDetector
{
    /** Browser is the deliberate fallback whenever the request is ambiguous. */
    public function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        $ua = strtolower((string) $request->userAgent());
        $signature = strtolower((string) $request->header('X-Subscription-Client', ''));

        if ($signature !== '') return true;
        if (str_contains($accept, 'application/json') || str_contains($accept, '+json')) return true;

        foreach ([
            'hiddify', 'v2ray', 'v2box', 'sing-box', 'singbox', 'clash', 'shadowrocket',
            'streisand', 'nekobox', 'nekoray', 'stash', 'surge', 'quantumult', 'loon',
        ] as $needle) {
            if (str_contains($ua, $needle)) return true;
        }

        if (str_contains($accept, 'text/html')) return false;
        if (str_contains($ua, 'mozilla/') || str_contains($ua, 'chrome/') || str_contains($ua, 'safari/')) return false;

        return false;
    }
}
