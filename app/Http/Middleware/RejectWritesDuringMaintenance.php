<?php
namespace App\Http\Middleware;

use App\Services\MaintenanceLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectWritesDuringMaintenance
{
    public function __construct(private readonly MaintenanceLockService $locks) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || !$this->locks->isLocked()) return $next($request);
        abort(503, 'System maintenance is in progress. Please retry shortly.');
    }
}
