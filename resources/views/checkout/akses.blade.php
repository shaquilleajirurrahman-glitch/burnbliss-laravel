@extends('layouts.shop')

@section('title', 'Buka pesanan')

@section('content')
<section class="container py-5">
    <div class="card bb-card mx-auto" style="max-width: 30rem;">
        <div class="card-body p-4 p-md-5">
            <i class="bi bi-shield-lock display-6 text-maroon" aria-hidden="true"></i>
            <h1 class="h4 mt-3 mb-2">Buka pesanan {{ $order->kode_pesanan }}</h1>
            <p class="text-body-secondary mb-4">
                Untuk melindungi data pemesan, masukkan nomor HP yang dipakai saat memesan.
            </p>

            <form method="POST" action="{{ route('checkout.akses.verify', $order) }}" class="bb-form" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="nomor_hp" class="form-label">Nomor HP pemesan</label>
                    <input type="tel" id="nomor_hp" name="nomor_hp" value="{{ old('nomor_hp') }}"
                           class="form-control @error('nomor_hp') is-invalid @enderror"
                           inputmode="tel" autocomplete="tel" placeholder="0812 3456 7890" autofocus required>
                    @error('nomor_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-maroon w-100 py-2">Buka Pesanan</button>
            </form>
        </div>
    </div>
</section>
@endsection
