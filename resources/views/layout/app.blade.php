<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SmartKas') }} - Kelola Keuangan Bisnis</title>
    <meta name="description" content="Platform manajemen kas modern untuk bisnis Indonesia. Catat transaksi, pantau arus kas, dan analisis keuangan secara real-time.">
    <meta name="author" content="SmartKas">
    <meta property="og:title" content="SmartKas - Manajemen Keuangan Bisnis">
    <meta property="og:description" content="Catat transaksi, pantau arus kas, dan analisis keuangan secara real-time.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="theme-color" content="#111111">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
    @include('components.navbar')

    <main class="flex-fill">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
