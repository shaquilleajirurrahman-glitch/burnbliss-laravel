@extends('layouts.shop')

@section('title', 'Menu')

@section('content')
<section class="container py-5">
    <header class="bb-catalog-head mb-4 mb-lg-5">
        <p class="bb-eyebrow text-maroon mb-2">Pre-order · Dipanggang segar setiap hari</p>
        <h1 class="bb-serif display-5 mb-3">Our Collection of Love</h1>
        <p class="lead text-body-secondary mb-0">
            Pilih varian favorit, masukkan ke keranjang, lalu pesan tanpa perlu membuat akun.
        </p>
    </header>

    @forelse ($products as $product)
        {{-- Varian pertama tampil sebagai kartu fitur lebar, sisanya dalam grid. --}}
        <div class="row g-4 mb-5">
            <div class="col-12">
                @include('catalog._featured', ['variant' => $product->variants->first()])
            </div>

            @foreach ($product->variants->skip(1) as $variant)
                <div class="col-sm-6 col-lg-4">
                    @include('catalog._card', ['variant' => $variant])
                </div>
            @endforeach
        </div>
    @empty
        <p class="text-center text-body-secondary py-5">Belum ada produk yang dijual.</p>
    @endforelse
</section>
@endsection
