<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="bb-body d-flex flex-column">
    {{-- Navbar toko publik. $cartCount disuntik oleh View::composer di AppServiceProvider. --}}
    <nav class="navbar navbar-expand bb-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand bb-brand" href="{{ route('catalog.index') }}">Burn &amp; Bliss</a>

            <ul class="navbar-nav bb-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a href="{{ route('catalog.index') }}"
                       @class(['nav-link', 'active' => request()->routeIs('catalog.*')])
                       @if (request()->routeIs('catalog.*')) aria-current="page" @endif>Menu</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('track.index') }}"
                       @class(['nav-link', 'active' => request()->routeIs('track.*')])
                       @if (request()->routeIs('track.*')) aria-current="page" @endif>
                        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Lacak<span class="d-none d-sm-inline"> Pesanan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('cart.index') }}" data-cart-link
                       @class(['nav-link bb-cart-link', 'active' => request()->routeIs('cart.*')])
                       @if (request()->routeIs('cart.*')) aria-current="page" @endif
                       aria-label="Keranjang, {{ $cartCount }} item">
                        <i class="bi bi-bag" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Keranjang</span>
                        <span @class(['badge rounded-pill bb-cart-badge js-cart-count', 'd-none' => $cartCount === 0])
                              aria-hidden="true">{{ $cartCount }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="flex-grow-1">
        @yield('content')
    </main>

    <footer class="bb-footer mt-5">
        <div class="container py-4 d-flex flex-column flex-md-row gap-3 justify-content-between small">
            <div>
                <span class="bb-brand fs-6">Burn &amp; Bliss</span>
                <span class="text-body-secondary ms-2">Jl. Halimun Raya No.2, Setiabudi, Jakarta Selatan</span>
            </div>
            <div class="text-body-secondary">
                Ambil di toko: Sen–Jum 09.00–18.00 · Sab 10.00–16.00
                <span class="mx-2">·</span>
                <a href="{{ route('track.index') }}" class="text-body-secondary">Lacak pesanan</a>
                <span class="mx-2">·</span>
                <a href="{{ route('login') }}" class="text-body-secondary">Masuk staf</a>
            </div>
        </div>
    </footer>

    @include('partials.scripts', ['showErrors' => true])
    <script src="{{ asset('js/cart.js') }}?v={{ filemtime(public_path('js/cart.js')) }}"></script>
</body>
</html>
