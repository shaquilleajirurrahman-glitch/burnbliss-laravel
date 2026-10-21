{{--
    Kartu sambutan dashboard sementara (Fase 2).
    Variabel: $panel (string) nama panel yang sedang dibuka (bisa berbeda dengan role user,
              karena atasan boleh membuka panel bawahan); $fitur (array<string>) fitur panel ini.
--}}
@php($user = auth()->user())

<div class="container py-5">
    <div class="card bb-card mb-4">
        <div class="card-body p-4 p-md-5">
            <p class="bb-eyebrow text-maroon mb-2">{{ $panel }}</p>
            <h1 class="bb-serif h2 mb-0">Selamat datang, {{ $user->name }} - Role: {{ $user->role->label() }}</h1>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-body-secondary mb-3">Segera hadir di panel ini</h2>
                    <ul class="list-unstyled mb-0 bb-feature-list">
                        @foreach ($fitur as $item)
                            <li><i class="bi bi-dot"></i>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-body-secondary mb-3">Uji hak akses</h2>
                    <p class="small text-body-secondary">Hierarki Admin &gt; Koordinator &gt; Staf. Halaman di luar hak akses Anda menampilkan error 403.</p>
                    <div class="list-group list-group-flush">
                        <a class="list-group-item list-group-item-action bb-access-link" href="{{ route('admin.dashboard') }}">
                            /admin/dashboard <span class="small text-body-secondary">· admin</span>
                        </a>
                        <a class="list-group-item list-group-item-action bb-access-link" href="{{ route('koordinator.dashboard') }}">
                            /koordinator/dashboard <span class="small text-body-secondary">· admin, koordinator</span>
                        </a>
                        <a class="list-group-item list-group-item-action bb-access-link" href="{{ route('staf.dashboard') }}">
                            /staf/dashboard <span class="small text-body-secondary">· admin, koordinator, staf</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
