@extends('layout.app')

@section('content')
<div class="container py-5">
    <p class="eyebrow">Admin</p>
    <h1 class="section-title h4 mb-4">Kelola</h1>

    @include('components.alerts')

    <div class="row g-3 mb-4">
        @include('components.stat', ['label' => 'Total anggota', 'value' => $counts['members']])
        @include('components.stat', ['label' => 'Pending', 'value' => $counts['pending']])
        @include('components.stat', ['label' => 'Belum bayar', 'value' => $counts['unpaid']])
    </div>

    <div class="card rounded-4 mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-semibold mb-0">Menunggu verifikasi ({{ $pendingUsers->count() }})</h2>
                <form method="POST" action="{{ route('admin.reminder') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">Kirim reminder</button>
                </form>
            </div>
            @forelse ($pendingUsers as $pu)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                    <div class="small"><span class="fw-semibold">{{ $pu->name }}</span> <span class="text-muted">· {{ $pu->nim }}</span></div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.users.verify', $pu) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-cta btn-sm">Verifikasi</button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.reject', $pu) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">Tolak</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">Tidak ada pendaftar menunggu.</p>
            @endforelse
        </div>
    </div>

    <div class="card rounded-4 mb-3">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Paket kas</h2>
            @forelse ($kasTypes as $kas)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                    <div class="small">
                        <span class="fw-semibold">{{ $kas->name }}</span>
                        <span class="text-muted">· @rupiah($kas->amount) · {{ $kas->bills_count }} tagihan · {{ $kas->is_active ? 'aktif' : 'nonaktif' }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.kas.update', $kas) }}" class="m-0">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $kas->is_active ? 0 : 1 }}">
                            <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">{{ $kas->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.kas.destroy', $kas) }}" class="m-0" onsubmit="return confirm('Hapus paket ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-3">Belum ada paket.</p>
            @endforelse
            <form method="POST" action="{{ route('admin.kas.store') }}" class="row g-2 mt-3">
                @csrf
                <div class="col-md-4">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama paket" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="amount" class="form-control form-control-sm" placeholder="Nominal" min="1" required>
                </div>
                <div class="col-md-3">
                    <input type="date" name="due_date" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-cta btn-sm">Buat</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Semua anggota</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-muted fw-normal">Nama</th>
                            <th scope="col" class="small text-muted fw-normal">NIM</th>
                            <th scope="col" class="small text-muted fw-normal text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="small fw-semibold">{{ $user->name }}</td>
                                <td class="small text-muted">{{ $user->nim }} · {{ $user->role }}</td>
                                <td class="text-end"><span class="badge text-bg-light border">{{ $user->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted small">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $users->links() }}</div>
        </div>
    </div>
</div>
@endsection
