{{--
    Form tambah/ubah produk + varian. Variabel: $product (Product, baru atau yang diubah), $action (URL).
    Setelah validasi gagal, baris varian diisi ulang dari old('variants').
--}}
@php
    $rows = old('variants') ?? ($product->exists
        ? $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'nama_varian' => $v->nama_varian,
            'harga_tambahan' => $v->harga_tambahan,
            'stok' => $v->stok,
        ])->all()
        : [['nama_varian' => '', 'harga_tambahan' => 0, 'stok' => 0]]);
    $nextIndex = $rows === [] ? 0 : max(array_map('intval', array_keys($rows))) + 1;
    $hargaDasar = (int) old('harga', $product->harga ?? 0);
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Periksa kembali isian Anda:</strong>
        <ul class="mb-0 mt-1 small">
            @foreach (collect($errors->all())->unique()->take(6) as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="bb-form" id="productForm" novalidate>
    @csrf
    @if ($product->exists)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Informasi produk</h2>

                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama produk</label>
                        <input type="text" id="nama" name="nama" value="{{ old('nama', $product->nama) }}"
                               class="form-control @error('nama') is-invalid @enderror" maxlength="120" required>
                        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="4" maxlength="2000"
                                  class="form-control @error('deskripsi') is-invalid @enderror">{{ old('deskripsi', $product->deskripsi) }}</textarea>
                        @error('deskripsi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="harga" class="form-label">Harga dasar</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="harga" name="harga" value="{{ $hargaDasar }}" min="0" step="500"
                                   class="form-control @error('harga') is-invalid @enderror" required>
                            @error('harga')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">Harga jual tiap varian = harga dasar + tambahan harga varian.</div>
                    </div>

                    <div>
                        <label for="gambar" class="form-label">
                            Foto produk @if ($product->exists)<span class="text-body-secondary">(kosongkan jika tidak diganti)</span>@endif
                        </label>
                        <input type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp"
                               class="form-control js-image-input @error('gambar') is-invalid @enderror" data-preview="#gambarPreview"
                               @required(! $product->exists)>
                        @error('gambar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB. Dipakai jika varian tidak punya foto sendiri.</div>
                        @enderror
                        <img id="gambarPreview" src="{{ $product->gambar_url ?? '' }}" alt="Pratinjau foto produk"
                             @class(['bb-bukti-preview rounded-3 mt-3', 'd-none' => ! $product->gambar_url])>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Varian</h2>
                        <button type="button" class="btn btn-sm btn-outline-maroon js-add-variant">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah varian
                        </button>
                    </div>
                    @error('variants')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                    <div id="variantRows" data-next-index="{{ $nextIndex }}">
                        @foreach ($rows as $i => $row)
                            @include('admin.products._variant-row')
                        @endforeach
                    </div>

                    @if ($product->exists)
                        <p class="small text-body-secondary mb-0 mt-2">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            Varian yang dihapus dari daftar ini akan dinonaktifkan saat disimpan (riwayat pesanan tetap aman).
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('internal.products.index') }}" class="btn btn-link text-body-secondary">Batal</a>
        <button type="submit" class="btn btn-maroon px-4">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ $product->exists ? 'Simpan Perubahan' : 'Simpan Produk' }}
        </button>
    </div>
</form>

<template id="variantTemplate">
    @include('admin.products._variant-row', ['i' => '__INDEX__', 'row' => []])
</template>

@push('scripts')
    <script src="{{ asset('js/admin-products.js') }}?v={{ filemtime(public_path('js/admin-products.js')) }}"></script>
@endpush
