@extends('layout.app')

@section('content')
<div class="container py-5">
    @include('components.alerts')

    <p class="eyebrow">Beranda</p>
    <h1 class="section-title h4 mb-4">Ringkasan</h1>

    @auth
        <div class="card rounded-4 mb-3 border-primary">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3">Tagihan saya yang belum bayar</h2>
                @forelse ($myBills as $bill)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                        <div class="small">
                            <span class="fw-semibold">{{ $bill->kasType->name ?? 'Tagihan' }}</span>
                            <span class="text-muted">· @rupiah($bill->amount)</span>
                        </div>
                        @if ($bill->kasType?->is_active)
                            <a href="{{ route('bayar.show', $bill) }}" class="btn btn-primary btn-cta btn-sm">Bayar sekarang</a>
                        @else
                            <span class="badge text-bg-light border">Nonaktif</span>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Lunas semua. Tidak ada yang perlu dibayar.</p>
                @endforelse
            </div>
        </div>
    @endauth

    <div class="row g-3 mb-4">
        @include('components.stat', ['wrap' => 'col-6 col-md-3', 'label' => 'Anggota', 'value' => $stats['members']])
        @include('components.stat', ['wrap' => 'col-6 col-md-3', 'label' => 'Belum bayar', 'value' => $stats['unpaid']])
        @include('components.stat', ['wrap' => 'col-6 col-md-3', 'label' => 'Lunas', 'value' => $stats['paid']])
        @include('components.stat', ['wrap' => 'col-6 col-md-3', 'label' => 'Terkumpul', 'value' => $stats['collected'], 'rupiah' => true])
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-semibold mb-0">Tagihan terbaru</h2>
                        <a href="{{ route('kas') }}" class="small text-decoration-none">Semua</a>
                    </div>
                    @forelse ($recentBills as $bill)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold small">{{ $bill->user->name ?? '-' }} <span class="text-muted fw-normal">· {{ $bill->user->nim ?? '' }}</span></div>
                                <div class="text-muted small">{{ $bill->kasType->name ?? '' }} · @rupiah($bill->amount)</div>
                            </div>
                            <span class="badge text-bg-light border">{{ $bill->status }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada tagihan.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3">Paket aktif</h2>
                    @forelse ($kasTypes as $kas)
                        <div class="py-2 border-bottom">
                            <div class="fw-semibold small">{{ $kas->name }}</div>
                            <div class="text-muted small">@rupiah($kas->amount) · jatuh {{ $kas->due_date?->format('d M Y') }}</div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Tidak ada paket aktif.</p>
                    @endforelse
                    <div class="d-grid gap-2 mt-3">
                        <a href="{{ route('kas') }}" class="btn btn-primary btn-cta">Lihat kas</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
