@extends('layouts.app')

@section('title', 'Kelola Produk')

@use('App\Support\Rupiah')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="bb-eyebrow text-maroon mb-1">Panel Internal</p>
            <h1 class="bb-serif h2 mb-0">Kelola Produk</h1>
            <p class="small text-body-secondary mb-0 mt-1">Produk & varian yang tampil di katalog pelanggan.</p>
        </div>
        <a href="{{ route('internal.products.create') }}" class="btn btn-maroon">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Produk
        </a>
    </div>

    <div class="card bb-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 bb-table">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col" class="text-end">Harga dasar</th>
                        <th scope="col">Varian · harga jual · stok</th>
                        <th scope="col" class="text-end">Total stok</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr id="product-{{ $product->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($product->gambar_url)
                                        <img src="{{ $product->gambar_url }}" alt="" width="56" height="56" class="rounded-3 object-fit-cover bb-cart-thumb">
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $product->nama }}</div>
                                        <div class="small text-body-secondary bb-clamp">{{ $product->deskripsi }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">{{ Rupiah::format($product->harga) }}</td>
                            <td>
                                <ul class="list-unstyled mb-0 small bb-variant-list">
                                    @foreach ($product->variants as $variant)
                                        @php($variant->setRelation('product', $product))
                                        <li>
                                            <span class="fw-semibold">{{ $variant->nama_varian }}</span>
                                            <span class="text-body-secondary text-nowrap">{{ Rupiah::format($variant->harga_akhir) }}</span>
                                            <span class="text-nowrap">
                                                @if ($variant->stok <= 5)
                                                    @include('catalog._stock')
                                                @else
                                                    Stok {{ $variant->stok }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-end fw-semibold">{{ $product->variants->sum('stok') }}</td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('internal.products.edit', $product) }}" class="btn btn-sm btn-outline-maroon">
                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Ubah
                                    </a>
                                    <form method="POST" action="{{ route('internal.products.destroy', $product) }}" class="js-confirm"
                                          data-confirm="Hapus produk &quot;{{ $product->nama }}&quot; beserta {{ $product->variants->count() }} variannya? Produk tidak akan tampil lagi di katalog, tetapi riwayat pesanan tetap tersimpan.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-body-secondary bb-remove">
                                            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-5">
                                <i class="bi bi-box-seam d-block fs-2 mb-2" aria-hidden="true"></i>
                                Belum ada produk. <a href="{{ route('internal.products.create') }}" class="bb-link">Tambah produk pertama</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
