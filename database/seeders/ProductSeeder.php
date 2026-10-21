<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    /**
     * Harga mengikuti prototipe lama: Original Rp 23.000, varian lain Rp 25.000 (per slice).
     */
    public function run(): void
    {
        $this->salinFotoProduk();

        $product = Product::create([
            'nama' => 'Basque Burnt Cheesecake',
            'deskripsi' => 'Basque burnt cheesecake khas Burn & Bliss dengan permukaan karamel gosong '
                .'dan bagian dalam yang lembut meleleh. Dipanggang segar setiap hari (sistem pre-order).',
            'harga' => 23000,
            'gambar' => 'products/original.jpg',
        ]);

        $product->variants()->createMany([
            ['nama_varian' => 'Original', 'gambar' => 'products/original.jpg', 'stok' => 20, 'harga_tambahan' => 0],
            ['nama_varian' => 'Oreo', 'gambar' => 'products/oreo.jpg', 'stok' => 20, 'harga_tambahan' => 2000],
            ['nama_varian' => 'Matcha', 'gambar' => 'products/matcha.jpg', 'stok' => 20, 'harga_tambahan' => 2000],
            ['nama_varian' => 'Brownie', 'gambar' => 'products/brownie.jpg', 'stok' => 20, 'harga_tambahan' => 2000],
        ]);
    }

    /**
     * Foto contoh disimpan di database/seeders/images (ikut Git) lalu disalin ke disk "public",
     * karena storage/app/public tidak di-commit. Butuh `sail artisan storage:link` sekali.
     */
    private function salinFotoProduk(): void
    {
        foreach (File::files(database_path('seeders/images')) as $file) {
            Storage::disk('public')->putFileAs('products', $file->getPathname(), $file->getFilename());
        }
    }
}
