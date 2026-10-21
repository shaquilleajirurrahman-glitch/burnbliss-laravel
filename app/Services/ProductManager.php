<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Simpan & hapus produk beserta variannya (dipakai Admin\ProductController).
 */
class ProductManager
{
    private const DISK = 'public';

    private const FOLDER = 'products';

    /**
     * Buat/ubah produk dan sinkronkan variannya dalam satu transaksi:
     *  - baris varian dengan id  -> diperbarui,
     *  - baris tanpa id          -> varian baru,
     *  - varian lama yang tidak dikirim lagi -> di-soft-delete (riwayat pesanan tetap utuh).
     *
     * @param  array{nama: string, deskripsi?: ?string, harga: int, variants: array<int|string, array{id?: ?int, nama_varian: string, harga_tambahan: int, stok: int}>}  $data
     * @param  array<int|string, UploadedFile|null>  $gambarVarian  key sama dengan $data['variants']
     */
    public function save(Product $product, array $data, ?UploadedFile $gambar, array $gambarVarian = []): Product
    {
        $fileBaru = [];  // dihapus lagi jika transaksi gagal
        $fileLama = [];  // dihapus setelah transaksi berhasil (jika tidak dipakai lagi)

        try {
            DB::transaction(function () use ($product, $data, $gambar, $gambarVarian, &$fileBaru, &$fileLama) {
                $product->fill([
                    'nama' => $data['nama'],
                    'deskripsi' => $data['deskripsi'] ?? null,
                    'harga' => $data['harga'],
                ]);

                if ($gambar) {
                    $fileLama[] = $product->gambar;
                    $product->gambar = $fileBaru[] = $gambar->store(self::FOLDER, self::DISK);
                }

                $product->save();

                $dipertahankan = [];
                foreach ($data['variants'] as $key => $row) {
                    $variant = empty($row['id'])
                        ? $product->variants()->make()
                        : $product->variants()->findOrFail($row['id']);

                    $variant->fill([
                        'nama_varian' => $row['nama_varian'],
                        'harga_tambahan' => $row['harga_tambahan'],
                        'stok' => $row['stok'],
                    ]);

                    if ($file = $gambarVarian[$key] ?? null) {
                        $fileLama[] = $variant->gambar;
                        $variant->gambar = $fileBaru[] = $file->store(self::FOLDER, self::DISK);
                    }

                    $variant->save();
                    $dipertahankan[] = $variant->id;
                }

                $product->variants()->whereNotIn('id', $dipertahankan)->get()->each->delete();
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($fileBaru);

            throw $e;
        }

        foreach (array_filter($fileLama) as $path) {
            $this->hapusJikaTidakDipakai($path);
        }

        return $product->load('variants');
    }

    /**
     * Soft delete produk & semua variannya: hilang dari katalog/keranjang,
     * tetapi riwayat pesanan dan laporan tetap bisa membaca datanya. File foto tidak dihapus.
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->variants()->get()->each->delete();
            $product->delete();
        });
    }

    /**
     * Foto contoh dari seeder bisa dipakai bersama oleh produk & varian, jadi file lama
     * hanya dihapus jika sudah tidak dirujuk siapa pun (termasuk yang di-soft-delete).
     */
    private function hapusJikaTidakDipakai(string $path): void
    {
        $masihDipakai = Product::withTrashed()->where('gambar', $path)->exists()
            || ProductVariant::withTrashed()->where('gambar', $path)->exists();

        if (! $masihDipakai) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
