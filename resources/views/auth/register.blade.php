@extends('layout.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <p class="eyebrow text-center">Daftar</p>
            <h1 class="section-title h4 text-center mb-4">Buat akun</h1>
            @include('components.alerts')
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="nim" class="form-label small">NIM</label>
                            <input type="text" class="form-control" id="nim" name="nim" value="{{ old('nim') }}" placeholder="cth: H1H024008" required>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label small">Nama</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label small">Kata sandi</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label small">Ulangi sandi</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-cta w-100">Daftar</button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">Sudah punya akun? <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">Masuk</a></p>
        </div>
    </div>
</div>
@endsection
