{{--
    Satu baris varian di form produk. Juga dipakai sebagai <template> (dengan $i = '__INDEX__')
    yang di-clone oleh public/js/admin-products.js saat menekan "Tambah varian".
    Variabel: $i (int|string), $row (array input), $product (Product), $hargaDasar (int).
--}}
@use('App\Support\Rupiah')

@php
    $model = ! empty($row['id']) ? $product->variants->firstWhere('id', (int) $row['id']) : null;
    $tambahan = (int) ($row['harga_tambahan'] ?? 0);
@endphp

<div class="bb-variant-row js-variant-row" data-index="{{ $i }}">
    <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">

    <div class="row g-2">
        <div class="col-sm-6">
            <label class="form-label small" for="v-{{ $i }}-nama">Nama varian</label>
            <input type="text" id="v-{{ $i }}-nama" name="variants[{{ $i }}][nama_varian]" value="{{ $row['nama_varian'] ?? '' }}"
                   class="form-control @error("variants.{$i}.nama_varian") is-invalid @enderror" maxlength="60" placeholder="mis. Matcha" required>
            @error("variants.{$i}.nama_varian")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-sm-6">
            <label class="form-label small" for="v-{{ $i }}-tambahan">Tambahan harga</label>
            <div class="input-group has-validation">
                <span class="input-group-text">Rp</span>
                <input type="number" id="v-{{ $i }}-tambahan" name="variants[{{ $i }}][harga_tambahan]" value="{{ $tambahan }}"
                       class="form-control js-tambahan @error("variants.{$i}.harga_tambahan") is-invalid @enderror" min="0" step="500" required>
                @error("variants.{$i}.harga_tambahan")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text">Harga jual: <strong class="js-harga-jual">{{ Rupiah::format($hargaDasar + $tambahan) }}</strong></div>
        </div>

        <div class="col-sm-4">
            <label class="form-label small" for="v-{{ $i }}-stok">Stok</label>
            <input type="number" id="v-{{ $i }}-stok" name="variants[{{ $i }}][stok]" value="{{ $row['stok'] ?? 0 }}"
                   class="form-control @error("variants.{$i}.stok") is-invalid @enderror" min="0" max="9999" required>
            @error("variants.{$i}.stok")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-sm-8">
            <label class="form-label small" for="v-{{ $i }}-gambar">Foto <span class="text-body-secondary">(opsional)</span></label>
            <div class="d-flex align-items-center gap-2">
                @if ($model?->gambar)
                    <img src="{{ $model->gambar_url }}" alt="" width="38" height="38" class="rounded-2 object-fit-cover flex-shrink-0">
                @endif
                <input type="file" id="v-{{ $i }}-gambar" name="variants[{{ $i }}][gambar]" accept="image/jpeg,image/png,image/webp"
                       class="form-control form-control-sm @error("variants.{$i}.gambar") is-invalid @enderror">
            </div>
            @error("variants.{$i}.gambar")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <button type="button" class="btn btn-sm btn-link bb-remove px-0 mt-1 js-remove-variant">
        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Hapus varian
    </button>
</div>
