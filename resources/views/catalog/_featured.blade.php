{{-- Kartu fitur (lebar) untuk varian signature. Variabel: $variant (dengan relasi product). --}}
@use('App\Support\Rupiah')

<article class="card bb-card bb-product bb-featured overflow-hidden">
    <div class="row g-0">
        <div class="col-md-6">
            <div class="bb-product-img bb-featured-img h-100">
                @if ($variant->gambar_url)
                    <img src="{{ $variant->gambar_url }}" alt="Cheesecake {{ $variant->nama_varian }}"
                         class="w-100 h-100 object-fit-cover">
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-body d-flex flex-column h-100 p-4 p-lg-5">
                <span class="badge bb-badge-role align-self-start mb-3">Signature</span>
                <p class="bb-eyebrow text-body-secondary mb-1">{{ $variant->product->nama }}</p>
                <h2 class="bb-serif display-6 mb-3">{{ $variant->nama_varian }}</h2>
                <p class="text-body-secondary mb-4">{{ $variant->product->deskripsi }}</p>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="bb-price fs-4">{{ Rupiah::format($variant->harga_akhir) }}</span>
                    @include('catalog._stock')
                </div>

                <div class="mt-auto">
                    @include('catalog._add-form')
                </div>
            </div>
        </div>
    </div>
</article>
