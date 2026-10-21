<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tambah (POST) & ubah (PUT) produk beserta variannya.
 * Hak akses dicek middleware can:... (ProductPolicy).
 */
class SaveProductRequest extends FormRequest
{
    private const GAMBAR = ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->map(fn ($row) => is_array($row) ? array_merge($row, ['nama_varian' => trim((string) ($row['nama_varian'] ?? ''))]) : $row)
            ->all();

        $this->merge([
            'nama' => trim((string) $this->input('nama')),
            'variants' => $variants,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $sedangUbah = $product instanceof Product;

        return [
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'harga' => ['required', 'integer', 'min:0', 'max:10000000'],
            // Foto wajib saat produk baru agar katalog tidak kosong; saat ubah boleh tidak diganti.
            'gambar' => [$sedangUbah ? 'nullable' : 'required', ...self::GAMBAR],

            'variants' => ['required', 'array', 'min:1', 'max:10'],
            'variants.*.id' => $sedangUbah
                ? ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)->whereNull('deleted_at')]
                : ['prohibited'],
            'variants.*.nama_varian' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'variants.*.harga_tambahan' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.stok' => ['required', 'integer', 'min:0', 'max:9999'],
            'variants.*.gambar' => ['nullable', ...self::GAMBAR],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama produk wajib diisi.',
            'nama.max' => 'Nama produk maksimal :max karakter.',
            'deskripsi.max' => 'Deskripsi maksimal :max karakter.',
            'harga.required' => 'Harga dasar wajib diisi.',
            'harga.integer' => 'Harga dasar harus berupa angka (tanpa titik).',
            'harga.min' => 'Harga dasar tidak boleh negatif.',
            'harga.max' => 'Harga dasar terlalu besar.',
            'gambar.required' => 'Foto produk wajib diunggah.',
            'gambar.image' => 'Foto produk harus berupa gambar.',
            'gambar.mimes' => 'Foto produk harus JPG, PNG, atau WEBP.',
            'gambar.max' => 'Ukuran foto produk maksimal 2 MB.',

            'variants.required' => 'Tambahkan minimal satu varian.',
            'variants.min' => 'Tambahkan minimal satu varian.',
            'variants.max' => 'Maksimal :max varian per produk.',
            'variants.*.id.exists' => 'Varian ke-:position tidak ditemukan pada produk ini.',
            'variants.*.id.prohibited' => 'Varian baru tidak boleh membawa ID.',
            'variants.*.nama_varian.required' => 'Varian ke-:position: nama wajib diisi.',
            'variants.*.nama_varian.max' => 'Varian ke-:position: nama maksimal :max karakter.',
            'variants.*.nama_varian.distinct' => 'Varian ke-:position: nama varian tidak boleh sama dengan varian lain.',
            'variants.*.harga_tambahan.required' => 'Varian ke-:position: tambahan harga wajib diisi (isi 0 jika sama dengan harga dasar).',
            'variants.*.harga_tambahan.integer' => 'Varian ke-:position: tambahan harga harus berupa angka.',
            'variants.*.harga_tambahan.min' => 'Varian ke-:position: tambahan harga tidak boleh negatif.',
            'variants.*.stok.required' => 'Varian ke-:position: stok wajib diisi.',
            'variants.*.stok.integer' => 'Varian ke-:position: stok harus berupa angka.',
            'variants.*.stok.min' => 'Varian ke-:position: stok tidak boleh negatif.',
            'variants.*.stok.max' => 'Varian ke-:position: stok maksimal :max.',
            'variants.*.gambar.image' => 'Varian ke-:position: foto harus berupa gambar.',
            'variants.*.gambar.mimes' => 'Varian ke-:position: foto harus JPG, PNG, atau WEBP.',
            'variants.*.gambar.max' => 'Varian ke-:position: ukuran foto maksimal 2 MB.',
        ];
    }

    /**
     * File foto per varian, dengan key yang sama seperti input variants.
     *
     * @return array<int|string, \Illuminate\Http\UploadedFile|null>
     */
    public function variantImages(): array
    {
        return collect($this->validated('variants'))
            ->mapWithKeys(fn ($row, $key) => [$key => $this->file("variants.{$key}.gambar")])
            ->all();
    }
}
