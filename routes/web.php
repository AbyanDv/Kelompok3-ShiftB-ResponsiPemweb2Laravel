<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\KasType\StoreKasTypeRequest;
use App\Http\Requests\Payment\ManualPaymentRequest;
use App\Models\Bill;
use App\Models\KasType;
use App\Models\Payment;
use App\Models\User;
use App\Services\DiscordReminder;
use App\Services\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

$admin = fn (Request $r) => abort_unless($r->user()?->isAdmin(), 403, 'Hanya admin.');
$ownBill = function (Request $request, Bill $bill): void {
    $user = $request->user();
    abort_unless($user->isAdmin() || $bill->user_id === $user->id, 403, 'Tagihan ini bukan milikmu.');
};
$ownPayment = function (Request $request, Payment $payment): void {
    $payment->loadMissing('bill');
    $user = $request->user();
    abort_unless($user->isAdmin() || $payment->bill->user_id === $user->id, 403, 'Pembayaran ini bukan milikmu.');
};

Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/masuk', fn () => Auth::check() ? redirect()->route('beranda') : view('auth.login'))->name('login');
Route::post('/masuk', function (LoginRequest $request) {
    $data = $request->validated();

    if (! Auth::attempt(['nim' => $data['nim'], 'password' => $data['password']], $request->boolean('remember'))) {
        return back()->withErrors(['nim' => 'NIM atau kata sandi salah.'])->withInput();
    }

    $request->session()->regenerate();

    return redirect()->route('beranda');
});

Route::get('/daftar', fn () => Auth::check() ? redirect()->route('beranda') : view('auth.register'))->name('register');
Route::post('/daftar', function (RegisterRequest $request) {
    $data = $request->validated();

    Auth::login(User::create([
        'nim' => $data['nim'],
        'name' => $data['name'],
        'email' => $data['nim'].'@smartkas.local',
        'password' => $data['password'],
        'role' => 'member',
        'status' => 'pending',
    ]));
    $request->session()->regenerate();

    return redirect()->route('beranda')->with('status', 'Pendaftaran berhasil. Menunggu verifikasi admin.');
});

Route::get('/beranda', function (Request $request) {
    return view('beranda', [
        'stats' => Bill::summary() + ['members' => User::verifiedMember()->count()],
        'kasTypes' => KasType::active()->latest()->take(5)->get(),
        'recentBills' => Bill::with(['user', 'kasType'])->latest()->take(5)->get(),
        'myBills' => $request->user()
            ? Bill::with('kasType')->where('user_id', $request->user()->id)->where('status', 'unpaid')->latest()->take(5)->get()
            : collect(),
    ]);
})->name('beranda');

Route::get('/kas', function (Request $request) {
    return view('kas', [
        'bills' => Bill::with(['user', 'kasType'])
            ->when($request->query('status'), fn ($q, $v) => $q->where('bills.status', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->whereHas('user',
                fn ($qq) => $qq->where('name', 'like', "%{$v}%")->orWhere('nim', 'like', "%{$v}%")))
            ->latest()->paginate(15)->withQueryString(),
        'summary' => Bill::summary(),
    ]);
})->name('kas');

Route::get('/bayar/{bill}', function (Bill $bill) {
    $bill->load(['user', 'kasType', 'payments' => fn ($q) => $q->latest()]);

    return view('bayar', ['bill' => $bill, 'pending' => $bill->payments->firstWhere('status', 'pending')]);
})->name('bayar.show');

Route::middleware('auth')->group(function () use ($admin, $ownBill, $ownPayment) {
    Route::post('/keluar', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');

    Route::post('/bayar/{bill}', function (Request $request, Bill $bill) use ($ownBill) {
        $ownBill($request, $bill);

        try {
            [$payment, $baru] = PaymentService::createPendingCharge($bill, app(PaymentGateway::class));
        } catch (HttpException $e) {
            return back()->withErrors(['bill' => $e->getMessage()]);
        }

        return back()->with('status', $baru ? 'Pembayaran dibuat. Pindai QRIS sebelum kedaluwarsa.' : 'Masih ada pembayaran menunggu.');
    })->name('bayar.store');

    Route::post('/bayar/payment/{payment}/simulasi', function (Request $request, Payment $payment) use ($ownPayment) {
        $ownPayment($request, $payment);

        if (app()->environment('production')) {
            abort(403, 'Simulasi dimatikan di production.');
        }

        if ($payment->status !== 'pending') {
            return back()->withErrors(['payment' => 'Hanya pembayaran menunggu yang bisa disimulasikan.']);
        }

        PaymentService::confirmPaid($payment, ['simulated' => true, 'order_id' => $payment->order_id, 'status' => 'paid']);

        return redirect()->route('bayar.show', $payment->bill_id)->with('status', 'Simulasi berhasil. Tagihan lunas.');
    })->name('bayar.simulate');

    Route::post('/bayar/payment/{payment}/batal', function (Request $request, Payment $payment) use ($ownPayment) {
        $ownPayment($request, $payment);

        if ($payment->status !== 'pending') {
            return back()->withErrors(['payment' => 'Hanya pembayaran menunggu yang bisa dibatalkan.']);
        }

        $payment->update(['status' => 'cancelled']);

        return back()->with('status', 'Pembayaran dibatalkan.');
    })->name('bayar.cancel');

    Route::prefix('admin')->name('admin.')->group(function () use ($admin) {
        Route::post('/bills/{bill}/tunai', function (ManualPaymentRequest $request, Bill $bill) use ($admin) {
            $admin($request);
            $data = $request->validated();

            try {
                PaymentService::recordCash($bill, $request->user(), $data['note'] ?? null);
            } catch (HttpException $e) {
                return back()->withErrors(['bill' => $e->getMessage()]);
            }

            return redirect()->route('bayar.show', $bill)->with('status', 'Tunai dicatat. Tagihan lunas.');
        })->name('bills.cash');

        Route::post('/users/{user}/verify', function (Request $request, User $user) use ($admin) {
            $admin($request);
            $user->update(['status' => 'verified']);
            $n = PaymentService::issueBillsForUser($user);

            return back()->with('status', $user->name." diverifikasi. {$n} tagihan diterbitkan.");
        })->name('users.verify');

        Route::post('/users/{user}/tolak', function (Request $request, User $user) use ($admin) {
            $admin($request);

            if ($request->user()->is($user)) {
                return back()->withErrors(['user' => 'Tidak dapat menolak akun sendiri.']);
            }

            $user->update(['status' => 'rejected']);

            return back()->with('status', $user->name.' ditolak.');
        })->name('users.reject');

        Route::post('/kas', function (StoreKasTypeRequest $request) use ($admin) {
            $admin($request);
            $data = $request->validated();

            $kas = null;
            $n = DB::transaction(function () use ($request, $data, &$kas) {
                $kas = KasType::create([...$data, 'is_active' => true, 'created_by' => $request->user()->id]);

                return PaymentService::issueBillsForKas($kas);
            });

            return back()->with('status', "Paket {$kas->name} dibuat. {$n} tagihan diterbitkan.");
        })->name('kas.store');

        Route::patch('/kas/{kasType}', function (Request $request, KasType $kasType) use ($admin) {
            $admin($request);
            $kasType->update(['is_active' => $request->boolean('is_active')]);

            return back()->with('status', 'Paket diperbarui.');
        })->name('kas.update');

        Route::delete('/kas/{kasType}', function (Request $request, KasType $kasType) use ($admin) {
            $admin($request);

            if ($kasType->bills()->where('status', 'paid')->exists()) {
                return back()->withErrors(['kas' => 'Sudah ada yang membayar. Nonaktifkan saja.']);
            }

            $kasType->delete();

            return back()->with('status', 'Paket dihapus.');
        })->name('kas.destroy');

        Route::post('/reminder', function (Request $request, DiscordReminder $reminder) use ($admin) {
            $admin($request);
            $result = $reminder->send();

            return back()->with($result['sent'] ? 'status' : 'warning', $result['message']);
        })->name('reminder');
    });

    Route::get('/admin', function (Request $request) use ($admin) {
        $admin($request);

        return view('admin', [
            'counts' => [
                'members' => User::where('role', 'member')->count(),
                'pending' => User::where('status', 'pending')->count(),
                'unpaid' => Bill::summary()['unpaid'],
            ],
            'users' => User::latest()->paginate(15),
            'pendingUsers' => User::where('status', 'pending')->latest()->take(20)->get(),
            'kasTypes' => KasType::withCount('bills')->latest()->get(),
        ]);
    })->name('admin');
});
