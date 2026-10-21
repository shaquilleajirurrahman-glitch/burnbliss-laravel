@extends('layouts.shop')

@section('title', 'Keranjang')

@use('App\Support\Rupiah')

@section('content')
<section class="container py-5">
    <h1 class="bb-serif display-6 mb-4">Keranjang</h1>

    {{-- Ditampilkan oleh cart.js saat item terakhir dihapus. --}}
    <div @class(['card bb-card text-center py-5 px-3 js-cart-empty', 'd-none' => $lines->isNotEmpty()])>
        <i class="bi bi-bag display-5 text-maroon" aria-hidden="true"></i>
        <p class="h5 mt-3 mb-1">Keranjang Anda masih kosong</p>
        <p class="text-body-secondary mb-4">Yuk, pilih cheesecake favorit Anda dulu.</p>
        <div><a href="{{ route('catalog.index') }}" class="btn btn-maroon px-4">Lihat Menu</a></div>
    </div>

    @if ($lines->isNotEmpty())
        <div class="row g-4 js-cart-filled">
            <div class="col-lg-8">
                <div class="card bb-card overflow-hidden">
                    <ul class="list-group list-group-flush">
                        @foreach ($lines as $line)
                            @php($variant = $line->variant)
                            <li class="list-group-item p-3 p-md-4 js-cart-line" data-variant-id="{{ $variant->id }}">
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    @if ($variant->gambar_url)
                                        <img src="{{ $variant->gambar_url }}" alt="" width="72" height="72"
                                             class="bb-cart-thumb rounded-3 object-fit-cover">
                                    @endif

                                    <div class="bb-cart-name flex-grow-1">
                                        <p class="fw-semibold mb-0">{{ $variant->nama_varian }}</p>
                                        <p class="small text-body-secondary mb-0">
                                            {{ $variant->product->nama }} · {{ Rupiah::format($line->hargaSatuan()) }}
                                        </p>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 ms-auto">
                                        <form method="POST" action="{{ route('cart.update') }}" class="js-cart-update">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                            <div class="input-group input-group-sm bb-stepper" role="group"
                                                 aria-label="Ubah jumlah {{ $variant->nama_varian }}">
                                                <button type="submit" name="jumlah" value="{{ $line->jumlah - 1 }}"
                                                        class="btn btn-outline-secondary js-minus" aria-label="Kurangi"
                                                        @disabled($line->jumlah <= 1)><i class="bi bi-dash"></i></button>
                                                <span class="input-group-text bb-stepper-qty js-qty" aria-live="polite">{{ $line->jumlah }}</span>
                                                <button type="submit" name="jumlah" value="{{ $line->jumlah + 1 }}"
                                                        class="btn btn-outline-secondary js-plus" aria-label="Tambah"
                                                        @disabled($line->jumlah >= $variant->stok)><i class="bi bi-plus"></i></button>
                                            </div>
                                        </form>

                                        <span class="bb-cart-subtotal fw-semibold text-end js-subtotal">{{ Rupiah::format($line->subtotal()) }}</span>

                                        <form method="POST" action="{{ route('cart.remove') }}" class="js-cart-remove">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                            <button type="submit" class="btn btn-link bb-remove p-1"
                                                    aria-label="Hapus {{ $variant->nama_varian }} dari keranjang">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <a href="{{ route('catalog.index') }}" class="bb-link small d-inline-block mt-3">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Lanjut belanja
                </a>
            </div>

            <div class="col-lg-4">
                <aside class="card bb-card bb-summary">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Ringkasan</h2>
                        <div class="d-flex justify-content-between text-body-secondary mb-2">
                            <span>Jumlah item</span>
                            <span class="js-cart-items">{{ $lines->sum(fn ($l) => $l->jumlah) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-baseline mb-4">
                            <span class="fw-semibold">Total</span>
                            <span class="bb-price fs-4 js-cart-total">{{ Rupiah::format($total) }}</span>
                        </div>
                        <a href="{{ route('checkout.index') }}" class="btn btn-maroon w-100 py-2">
                            Lanjut ke Checkout <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                        <p class="small text-body-secondary text-center mt-2 mb-0">Tanpa perlu membuat akun.</p>
                    </div>
                </aside>
            </div>
        </div>
    @endif
</section>
@endsection
