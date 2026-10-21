{{--
    Form "Tambah ke Keranjang". Tanpa JS: POST biasa (redirect + flash).
    Dengan JS: public/js/cart.js mencegat submit dan mengirim AJAX (JSON).
    Variabel: $variant, $diKeranjang (array variant_id => jumlah dari ProductController).
--}}
@php($jumlahDiKeranjang = $diKeranjang[$variant->id] ?? 0)

<form method="POST" action="{{ route('cart.add') }}" class="js-cart-add">
    @csrf
    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
    <button type="submit" class="btn btn-maroon w-100 py-2" @disabled($variant->stok === 0)>
        @if ($variant->stok === 0)
            Stok habis
        @else
            <i class="bi bi-bag-plus me-1" aria-hidden="true"></i>Tambah ke Keranjang
        @endif
    </button>
    <p @class(['small text-body-secondary text-center mt-2 mb-0 js-in-cart', 'd-none' => $jumlahDiKeranjang === 0])
       data-variant-id="{{ $variant->id }}">
        <i class="bi bi-check2 text-maroon" aria-hidden="true"></i>
        <span class="js-in-cart-qty">{{ $jumlahDiKeranjang }}</span> di keranjang
    </p>
</form>
