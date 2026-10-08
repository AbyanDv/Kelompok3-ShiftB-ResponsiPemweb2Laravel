@extends('layout.app')

@section('content')
<div class="container py-5">
    <p class="eyebrow">Kas</p>
    <h1 class="section-title h4 mb-4">Tagihan</h1>

    <div class="row g-3 mb-4">
        @include('components.stat', ['label' => 'Terkumpul', 'value' => $summary['collected'], 'rupiah' => true])
        @include('components.stat', ['label' => 'Lunas', 'value' => $summary['paid']])
        @include('components.stat', ['label' => 'Belum bayar', 'value' => $summary['unpaid']])
        @include('components.stat', ['label' => 'Pengeluaran', 'value' => $cash['expense'], 'rupiah' => true])
        @include('components.stat', ['label' => 'Saldo bersih', 'value' => $cash['balance'], 'rupiah' => true])
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <form class="row g-2 mb-3" method="GET" action="{{ route('kas') }}">
                <div class="col-md-6">
                    <input type="search" name="q" class="form-control" placeholder="Cari nama / NIM..." value="{{ request('q') }}">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (['unpaid', 'paid', 'cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-dark btn-cta">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-muted fw-normal">Anggota</th>
                            <th scope="col" class="small text-muted fw-normal">Paket</th>
                            <th scope="col" class="small text-muted fw-normal text-end">Nominal</th>
                            <th scope="col" class="small text-muted fw-normal text-end">Status</th>
                            <th scope="col" class="small text-muted fw-normal text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bills as $bill)
                            <tr>
                                <td>
                                    <a href="{{ route('bayar.show', $bill) }}" class="fw-semibold small text-decoration-none text-dark">{{ $bill->user->name ?? '-' }}</a>
                                    <div class="text-muted small">{{ $bill->user->nim ?? '' }}</div>
                                </td>
                                <td class="small text-muted">{{ $bill->kasType->name ?? '' }}</td>
                                <td class="text-end small">@rupiah($bill->amount)</td>
                                <td class="text-end"><span class="badge text-bg-light border">{{ $bill->status }}</span></td>
                                <td class="text-end">
                                    @if ($bill->status === 'unpaid' && $bill->kasType?->is_active)
                                        @auth
                                            <a href="{{ route('bayar.show', $bill) }}" class="btn btn-primary btn-cta btn-sm">Bayar</a>
                                        @else
                                            <a href="{{ route('login') }}" class="btn btn-outline-dark btn-cta btn-sm">Masuk</a>
                                        @endauth
                                    @elseif ($bill->status === 'unpaid')
                                        <span class="badge text-bg-light border">Nonaktif</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted small">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $bills->links() }}</div>
        </div>
    </div>
</div>
@endsection
