@extends('layout.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <p class="eyebrow text-center">Masuk</p>
            <h1 class="section-title h4 text-center mb-4">SmartKas</h1>
            @include('components.alerts')
            <div class="card rounded-4">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="nim" class="form-label small">NIM</label>
                            <input type="text" class="form-control" id="nim" name="nim" value="{{ old('nim') }}" placeholder="cth: H1H024001" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label small">Kata sandi</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                            <label class="form-check-label small" for="remember">Ingat saya</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-cta w-100">Masuk</button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">Belum punya akun? <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Daftar</a></p>
        </div>
    </div>
</div>
@endsection
