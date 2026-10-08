<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SmartKas membantu mencatat, mengelola, dan menganalisis arus kas secara real-time.">
    <title>SmartKas - Modern Cash Management</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-dark">
    <!-- Navigation -->
    <nav class="navbar fixed-top py-3 bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold fs-5 m-0" href="{{ url('/') }}">
                <span class="text-primary">Smart</span>Kas
            </a>
            @if (Route::has('login'))
                <div class="d-flex align-items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn-outline-dark btn-cta">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="nav-link p-0">Masuk</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-primary btn-cta">Daftar</a>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    </nav>

    <main>
        <!-- Hero -->
        <section class="min-vh-100 d-flex align-items-center pt-5 pb-5 mt-5">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <div class="section-divider"></div>
                        <h1 class="hero-title mb-4 text-balance">
                            Kelola keuangan bisnis dengan lebih cerdas
                        </h1>
                        <p class="hero-subtitle mb-5">
                            SmartKas membantu Anda mencatat, mengelola, dan menganalisis arus kas bisnis secara real-time. Tanpa ribet, tanpa drama.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-primary btn-cta btn-lg">Mulai gratis</a>
                            @endif
                            <a href="#features" class="btn btn-outline-dark btn-cta btn-lg">Pelajari lebih lanjut</a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <!-- Ilustrasi contoh tampilan (data contoh) -->
                        <div class="bg-light rounded-4 p-4 p-md-5 position-relative overflow-hidden" aria-hidden="true">
                            <div class="position-absolute top-0 end-0 w-75 h-75 bg-primary bg-opacity-10 rounded-circle"
                                 style="filter: blur(80px); transform: translate(30%, -30%);"></div>
                            <div class="position-relative z-1">
                                <div class="bg-white rounded-3 p-4 shadow-sm mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted small">Total saldo</span>
                                        <span class="badge bg-success-subtle text-success">+12,5%</span>
                                    </div>
                                    <p class="h3 fw-semibold mb-0">Rp 45.850.000</p>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="bg-white rounded-3 p-3 shadow-sm h-100">
                                            <span class="text-muted small d-block mb-1">Pemasukan</span>
                                            <span class="fw-semibold text-success">+ Rp 12.500.000</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-white rounded-3 p-3 shadow-sm h-100">
                                            <span class="text-muted small d-block mb-1">Pengeluaran</span>
                                            <span class="fw-semibold text-danger">- Rp 8.200.000</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Fitur -->
        <section id="features" class="py-5" style="scroll-margin-top: 5rem;">
            <div class="container py-lg-4">
                <div class="text-center mb-5">
                    <div class="section-divider mx-auto"></div>
                    <h2 class="section-title mb-3">Fitur unggulan</h2>
                    <p class="text-muted mx-auto mb-0" style="max-width: 480px;">
                        Semua yang Anda butuhkan untuk mengelola keuangan bisnis dalam satu platform.
                    </p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card card-hover h-100 border rounded-4">
                            <div class="card-body p-4">
                                <div class="bg-primary-subtle rounded-3 p-3 d-inline-block mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="text-primary d-block" viewBox="0 0 16 16" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M14 2.5a.5.5 0 0 0-.5-.5h-6a.5.5 0 0 0 0 1h4.793L8.146 10.146a.5.5 0 0 1-.708 0L6 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L6 7.293l1.146 1.147a.5.5 0 0 0 .708 0L14 2.707V6.5a.5.5 0 0 0 1 0v-4z"/>
                                        <path d="M2 2v12h12v-1H3V2H2z"/>
                                    </svg>
                                </div>
                                <h3 class="h5 fw-semibold mb-2">Analisis real-time</h3>
                                <p class="text-muted mb-0">Pantau arus kas secara langsung dengan visualisasi yang mudah dipahami.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-hover h-100 border rounded-4">
                            <div class="card-body p-4">
                                <div class="bg-success-subtle rounded-3 p-3 d-inline-block mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="text-success d-block" viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
                                        <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                                    </svg>
                                </div>
                                <h3 class="h5 fw-semibold mb-2">Aman dan terenkripsi</h3>
                                <p class="text-muted mb-0">Data keuangan Anda dilindungi dengan enkripsi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-hover h-100 border rounded-4">
                            <div class="card-body p-4">
                                <div class="bg-warning-subtle rounded-3 p-3 d-inline-block mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="text-warning d-block" viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"/>
                                    </svg>
                                </div>
                                <h3 class="h5 fw-semibold mb-2">Cepat dan efisien</h3>
                                <p class="text-muted mb-0">Catat transaksi dalam hitungan detik, akses dari mana saja.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistik -->
        <section class="py-5 bg-light">
            <div class="container py-lg-4">
                <div class="row g-4 text-center">
                    <div class="col-md-3 col-6">
                        <p class="h1 fw-bold text-primary mb-1">500+</p>
                        <p class="text-muted mb-0">Pengguna aktif</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <p class="h1 fw-bold text-primary mb-1">1 juta+</p>
                        <p class="text-muted mb-0">Transaksi dicatat</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <p class="h1 fw-bold text-primary mb-1">99,9%</p>
                        <p class="text-muted mb-0">Uptime</p>
                    </div>
                    <div class="col-md-3 col-6">
                        <p class="h1 fw-bold text-primary mb-1">24/7</p>
                        <p class="text-muted mb-0">Dukungan</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-5">
            <div class="container py-lg-4">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <div class="section-divider mx-auto"></div>
                        <h2 class="section-title mb-3">Siap memulai?</h2>
                        <p class="text-muted mb-4 mx-auto" style="max-width: 480px;">
                            Bergabung dengan ratusan pengguna yang sudah memakai SmartKas untuk mengelola keuangan mereka.
                        </p>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-primary btn-cta btn-lg">Daftar sekarang, gratis</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>