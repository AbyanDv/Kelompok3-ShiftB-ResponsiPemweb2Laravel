<nav class="navbar navbar-expand-lg py-3 bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold m-0" href="{{ url('/') }}">SmartKas</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNav"
            aria-controls="appNav" aria-expanded="false" aria-label="Navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="appNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1 small">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('beranda') ? 'active fw-semibold' : '' }}" href="{{ route('beranda') }}">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('kas') ? 'active fw-semibold' : '' }}" href="{{ route('kas') }}">Kas</a>
                </li>
                @auth
                    @if (auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin') ? 'active fw-semibold' : '' }}" href="{{ route('admin') }}">Admin</a>
                        </li>
                    @endif
                @endauth
            </ul>
            <div class="d-flex align-items-center gap-2">
                @auth
                    <span class="text-muted small">{{ auth()->user()->name }} · {{ auth()->user()->nim }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-dark btn-cta btn-sm">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="nav-link p-0 small">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-cta btn-sm">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
