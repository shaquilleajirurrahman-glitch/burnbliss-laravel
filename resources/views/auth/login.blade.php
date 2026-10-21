@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="container bb-auth-wrap">
    <div class="card bb-auth-card overflow-hidden">
        <div class="row g-0">
            @include('auth._panel')

            <div class="col-lg-7">
                <div class="card-body p-4 p-md-5">
                    <a href="{{ url('/') }}" class="bb-brand d-inline-block mb-4">Burn &amp; Bliss</a>
                    <h1 class="bb-serif h3 mb-1">Selamat datang kembali</h1>
                    <p class="text-body-secondary mb-4">Khusus Admin, Koordinator, dan Staf Burn &amp; Bliss.</p>

                    <form method="POST" action="{{ route('login') }}" class="bb-form" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   autocomplete="email" autofocus required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group has-validation">
                                <input type="password" id="password" name="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       autocomplete="current-password" required>
                                <button type="button" class="btn btn-outline-secondary bb-toggle-password"
                                        data-target="#password" aria-label="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">Ingat saya</label>
                        </div>

                        <button type="submit" class="btn btn-maroon w-100 py-2">Masuk</button>
                    </form>

                    <p class="text-center text-body-secondary small mt-4 mb-0">
                        Akun dibuat oleh Admin. Lupa password? Hubungi Admin toko.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
