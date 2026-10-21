<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="bb-body">
    @auth
        @php($role = auth()->user()->role)
        @php($panels = [
            ['label' => 'Admin Panel', 'route' => 'admin.dashboard', 'pattern' => 'admin.*', 'min' => \App\Enums\UserRole::Admin],
            ['label' => 'Koordinator Panel', 'route' => 'koordinator.dashboard', 'pattern' => 'koordinator.*', 'min' => \App\Enums\UserRole::Koordinator],
            ['label' => 'Staf Panel', 'route' => 'staf.dashboard', 'pattern' => 'staf.*', 'min' => \App\Enums\UserRole::Staf],
        ])
        <nav class="navbar navbar-expand-lg bb-navbar">
            <div class="container">
                <a class="navbar-brand bb-brand" href="{{ route($role->dashboardRoute()) }}">Burn &amp; Bliss</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bbNav"
                        aria-controls="bbNav" aria-expanded="false" aria-label="Buka menu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="bbNav">
                    {{-- Hierarki: Admin > Koordinator > Staf. Menu muncul jika role >= batas minimal panel. --}}
                    <ul class="navbar-nav me-auto bb-nav">
                        @can('viewFinancials', \App\Models\Order::class)
                            <li class="nav-item">
                                <a href="{{ route('internal.dashboard') }}"
                                   @class(['nav-link', 'active' => request()->routeIs('internal.dashboard')])
                                   @if (request()->routeIs('internal.dashboard')) aria-current="page" @endif>
                                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard
                                </a>
                            </li>
                        @endcan
                        <li class="nav-item">
                            <a href="{{ route('internal.orders.index') }}"
                               @class(['nav-link', 'active' => request()->routeIs('internal.orders.*')])
                               @if (request()->routeIs('internal.orders.*')) aria-current="page" @endif>
                                <i class="bi bi-receipt me-1" aria-hidden="true"></i>Pesanan
                            </a>
                        </li>
                        @can('viewFinancials', \App\Models\Order::class)
                            <li class="nav-item">
                                <a href="{{ route('internal.reports.index') }}"
                                   @class(['nav-link', 'active' => request()->routeIs('internal.reports.*')])
                                   @if (request()->routeIs('internal.reports.*')) aria-current="page" @endif>
                                    <i class="bi bi-file-earmark-bar-graph me-1" aria-hidden="true"></i>Laporan
                                </a>
                            </li>
                        @endcan
                        @php($bolehProduk = auth()->user()->can('viewAny', \App\Models\Product::class))
                        @php($bolehKuota = auth()->user()->can('viewAny', \App\Models\DailyQuota::class))
                        @php($bolehPengguna = auth()->user()->can('viewAny', \App\Models\User::class))
                        @if ($bolehProduk || $bolehKuota || $bolehPengguna)
                            <li class="nav-item dropdown">
                                <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                                   @class(['nav-link dropdown-toggle', 'active' => request()->routeIs('internal.products.*', 'internal.quotas.*', 'internal.users.*')])>
                                    <i class="bi bi-sliders me-1" aria-hidden="true"></i>Kelola
                                </a>
                                <ul class="dropdown-menu bb-dropdown">
                                    @if ($bolehProduk)
                                        <li>
                                            <a href="{{ route('internal.products.index') }}"
                                               @class(['dropdown-item', 'active' => request()->routeIs('internal.products.*')])>
                                                <i class="bi bi-box-seam me-2" aria-hidden="true"></i>Produk &amp; Varian
                                            </a>
                                        </li>
                                    @endif
                                    @if ($bolehKuota)
                                        <li>
                                            <a href="{{ route('internal.quotas.index') }}"
                                               @class(['dropdown-item', 'active' => request()->routeIs('internal.quotas.*')])>
                                                <i class="bi bi-calendar2-week me-2" aria-hidden="true"></i>Kuota Harian
                                            </a>
                                        </li>
                                    @endif
                                    @if ($bolehPengguna)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a href="{{ route('internal.users.index') }}"
                                               @class(['dropdown-item', 'active' => request()->routeIs('internal.users.*')])>
                                                <i class="bi bi-people me-2" aria-hidden="true"></i>Pengguna
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        {{-- Panel per role digabung dalam dropdown agar navbar tidak terlalu padat. --}}
                        @php($panelTerlihat = array_filter($panels, fn ($p) => $role->isAtLeast($p['min'])))
                        @php($panelAktif = collect($panelTerlihat)->contains(fn ($p) => request()->routeIs($p['pattern'])))
                        <li class="nav-item dropdown">
                            <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                               @class(['nav-link dropdown-toggle', 'active' => $panelAktif])>
                                <i class="bi bi-grid me-1" aria-hidden="true"></i>Panel
                            </a>
                            <ul class="dropdown-menu bb-dropdown">
                                @foreach ($panelTerlihat as $panel)
                                    <li>
                                        <a href="{{ route($panel['route']) }}"
                                           @class(['dropdown-item', 'active' => request()->routeIs($panel['pattern'])])
                                           @if (request()->routeIs($panel['pattern'])) aria-current="page" @endif>
                                            {{ $panel['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-3 py-2 py-lg-0">
                        <span class="small text-body-secondary text-nowrap">
                            {{ auth()->user()->name }}
                            <span class="badge bb-badge-role ms-1">{{ $role->label() }}</span>
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="m-0 ms-auto">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-maroon">
                                <i class="bi bi-box-arrow-right me-1"></i>Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <main>
        @yield('content')
    </main>

    @include('partials.scripts')
</body>
</html>
