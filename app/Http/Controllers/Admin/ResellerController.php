<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\ResellerManagementService;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;

final class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $query = Reseller::query()->with('wallet')->withCount(['stores','subscriptions']);
        if (($q = trim((string) $request->query('q'))) !== '') $query->where('name', 'like', '%'.$q.'%');
        return view('admin.resellers.index', ['resellers' => $query->latest('id')->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.resellers.create');
    }

    public function store(Request $request, ResellerManagementService $service)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:254','unique:users,email'],
            'password' => ['required','string','min:12','max:1024','confirmed'],
            'store_name' => ['required','string','max:120'],
        ]);
        $service->create($data['name'], $data['email'], $data['password'], $data['store_name']);
        return redirect()->route('admin.resellers.index')->with('success', 'نماینده، کیف پول و فروشگاه اولیه ایجاد شدند.');
    }

    public function topup(Request $request, int $reseller, WalletLedgerService $wallets)
    {
        Reseller::query()->findOrFail($reseller);
        $data = $request->validate(['amount_minor' => ['required','integer','min:1','max:9000000000000000000']]);
        $reference = 'manual:'.bin2hex(random_bytes(16));
        $wallets->credit($reseller, (int) $data['amount_minor'], 'manual_topup', $reference, 'Manual top-up by super admin');
        return back()->with('success', 'کیف پول شارژ شد.');
    }
}
