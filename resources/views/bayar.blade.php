@extends('layout.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <p class="eyebrow">Bayar</p>
            <h1 class="section-title h4 mb-4">{{ $bill->kasType->name ?? 'Tagihan' }}</h1>

            @include('components.alerts')

            @guest
                <div class="alert alert-light border small">
                    Ini tagihan {{ $bill->user->name ?? '' }}. <a href="{{ route('login') }}" class="fw-semibold">Masuk</a> dulu untuk membayar, atau <a href="{{ route('register') }}" class="fw-semibold">daftar</a> bila belum punya akun.
                </div>
            @endguest

            <div class="card rounded-4 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">{{ $bill->user->name ?? '' }} · {{ $bill->user->nim ?? '' }}</span>
                        <span class="badge text-bg-light border">{{ $bill->status }}</span>
                    </div>
                    <p class="h4 fw-semibold mb-1">@rupiah($bill->amount)</p>
                    <p class="text-muted small mb-0">Jatuh tempo: {{ $bill->kasType->due_date?->format('d M Y') ?? '-' }}</p>
                </div>
            </div>

            @if ($pending)
                <div class="card rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-2">Menunggu pembayaran</h2>
                        <p class="small text-muted mb-1">Order: {{ $pending->order_id }}</p>
                        <p class="small text-muted mb-1">Total: @rupiah($pending->total_amount)</p>
                        <p class="small text-muted mb-3">Kedaluarsa: {{ $pending->expires_at?->format('d M Y H:i') }}</p>
                        @if ($pending->qr_string)
                            <p class="small mb-2"><span class="text-muted">QR:</span> {{ $pending->qr_string }}</p>
                            <div id="sbx-qr" class="mb-1"></div>
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                            <script>
                                new QRCode(document.getElementById('sbx-qr'), {
                                    text: @json($pending->qr_string),
                                    width: 160,
                                    height: 160,
                                });
                            </script>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('bayar.simulate', $pending) }}" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-cta btn-sm">Simulasi lunas</button>
                            </form>
                            <form method="POST" action="{{ route('bayar.cancel', $pending) }}" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">Batalkan</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if ($bill->kasType && ! $bill->kasType->is_active)
                <div class="card rounded-4 mb-3" aria-disabled="true">
                    <div class="card-body p-4 text-center">
                        <span class="badge text-bg-light border mb-2">Nonaktif</span>
                        <p class="text-muted small mb-0">Paket ini ditutup admin. Pembayaran tidak dibuka.</p>
                    </div>
                </div>
            @else
                <div class="card rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-3">Buat pembayaran QRIS</h2>
                        <form method="POST" action="{{ route('bayar.store', $bill) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-cta w-100" {{ $bill->status !== 'unpaid' ? 'disabled' : '' }}>
                                {{ $bill->status !== 'unpaid' ? 'Tidak bisa dibayar ('.$bill->status.')' : 'Buat kode bayar' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if (auth()->user()?->isAdmin() && $bill->status === 'unpaid')
                <div class="card rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-3">Catat tunai (admin)</h2>
                        <form method="POST" action="{{ route('admin.bills.cash', $bill) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="note" class="form-label small">Catatan</label>
                                <input type="text" id="note" name="note" class="form-control" placeholder="Opsional">
                            </div>
                            <button type="submit" class="btn btn-outline-dark btn-cta w-100">Tandai lunas tunai</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bills.cancel', $bill) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-cta w-100" onclick="return confirm('Batalkan tagihan ini?')">Batalkan tagihan</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($bill->payments->count())
                <div class="card rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-3">Riwayat pembayaran</h2>
                        @foreach ($bill->payments as $pay)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div class="small">
                                    <span class="fw-semibold">{{ $pay->order_id }}</span>
                                    <span class="text-muted">· {{ $pay->channel }} · @rupiah($pay->total_amount)</span>
                                </div>
                                <span class="badge text-bg-light border">{{ $pay->status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="{{ route('kas') }}" class="btn btn-link btn-sm text-decoration-none mt-2">Kembali</a>
        </div>
    </div>
</div>
@endsection
