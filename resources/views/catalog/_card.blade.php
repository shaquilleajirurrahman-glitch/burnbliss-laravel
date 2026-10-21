{{-- Kartu varian di grid katalog. Variabel: $variant (dengan relasi product). --}}
@use('App\Support\Rupiah')

<article class="card bb-card bb-product h-100 overflow-hidden">
    <div class="ratio ratio-4x3 bb-product-img">
        @if ($variant->gambar_url)
            <img src="{{ $variant->gambar_url }}" alt="Cheesecake {{ $variant->nama_varian }}"
                 class="object-fit-cover" loading="lazy">
        @endif
    </div>
    <div class="card-body d-flex flex-column p-4">
        <p class="bb-eyebrow text-body-secondary mb-1">{{ $variant->product->nama }}</p>
        <h3 class="bb-serif h4 mb-3">{{ $variant->nama_varian }}</h3>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="bb-price">{{ Rupiah::format($variant->harga_akhir) }}</span>
            @include('catalog._stock')
        </div>

        <div class="mt-auto">
            @include('catalog._add-form')
        </div>
    </div>
</article>
