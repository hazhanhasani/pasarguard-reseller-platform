<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::query()->where('audience_type', 'super_admin');
        $state = (string) $request->query('state');
        if ($state === 'open') $query->whereNull('resolved_at');
        if ($state === 'resolved') $query->whereNotNull('resolved_at');
        return view('admin.notifications.index', [
            'notifications' => $query->latest('last_seen_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function markRead(int $notification)
    {
        $item = Notification::query()->where('audience_type', 'super_admin')->findOrFail($notification);
        if ($item->read_at === null) {
            $item->read_at = now();
            $item->save();
        }
        return back();
    }

    public function resolve(int $notification)
    {
        $item = Notification::query()->where('audience_type', 'super_admin')->findOrFail($notification);
        $item->resolved_at = now();
        if ($item->read_at === null) $item->read_at = now();
        $item->save();
        return back()->with('success', 'هشدار بسته شد.');
    }
}
