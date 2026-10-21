<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShippingMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->koordinator = User::factory()->role(UserRole::Koordinator)->create();
        $this->staf = User::factory()->role(UserRole::Staf)->create();
    }

    private function foto(string $nama = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 600, 600)->size(200);
    }

    /**
     * Produk awal: foto "products/lama.jpg" dipakai bersama oleh produk & varian Original (seperti seeder).
     */
    private function produkAwal(): Product
    {
        Storage::disk('public')->put('products/lama.jpg', 'foto-lama');

        $product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'deskripsi' => 'Khas Burn & Bliss.', 'harga' => 23000, 'gambar' => 'products/lama.jpg']);
        $product->variants()->createMany([
            ['nama_varian' => 'Original', 'stok' => 10, 'harga_tambahan' => 0, 'gambar' => 'products/lama.jpg'],
            ['nama_varian' => 'Oreo', 'stok' => 5, 'harga_tambahan' => 2000],
        ]);

        return $product->load('variants');
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function dataBaru(array $override = []): array
    {
        return array_replace([
            'nama' => 'Mini Cheesecake',
            'deskripsi' => 'Ukuran mini untuk hampers.',
            'harga' => 15000,
            'gambar' => $this->foto(),
            'variants' => [
                ['nama_varian' => 'Lotus', 'harga_tambahan' => 3000, 'stok' => 12, 'gambar' => $this->foto('lotus.jpg')],
                ['nama_varian' => 'Keju', 'harga_tambahan' => 0, 'stok' => 8],
            ],
        ], $override);
    }

    /**
     * Data form ubah untuk produk awal.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function dataUbah(Product $product, array $variants, array $override = []): array
    {
        return array_replace([
            'nama' => $product->nama,
            'deskripsi' => $product->deskripsi,
            'harga' => $product->harga,
            'variants' => $variants,
        ], $override);
    }

    // ── Hak akses ────────────────────────────────────────────

    public function test_hanya_admin_yang_bisa_mengelola_produk(): void
    {
        $product = $this->produkAwal();

        $this->get('/internal/produk')->assertRedirect('/login');

        foreach ([$this->koordinator, $this->staf] as $user) {
            $this->actingAs($user)->get('/internal/produk')->assertForbidden();
            $this->actingAs($user)->get('/internal/produk/create')->assertForbidden();
            $this->actingAs($user)->post('/internal/produk', $this->dataBaru())->assertForbidden();
            $this->actingAs($user)->get("/internal/produk/{$product->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/internal/produk/{$product->id}", $this->dataUbah($product, []))->assertForbidden();
            $this->actingAs($user)->delete("/internal/produk/{$product->id}")->assertForbidden();
        }

        $this->assertNotSoftDeleted($product);
        $this->assertDatabaseCount('products', 1);

        $this->actingAs($this->admin)->get('/internal/produk')->assertOk();
        $this->actingAs($this->admin)->get('/internal/produk/create')->assertOk();
        $this->actingAs($this->admin)->get("/internal/produk/{$product->id}/edit")->assertOk()->assertSee('Original')->assertSee('Oreo');
    }

    // ── Read ─────────────────────────────────────────────────

    public function test_daftar_produk_menampilkan_varian_harga_jual_dan_stok(): void
    {
        $product = $this->produkAwal();

        $this->actingAs($this->admin)->get('/internal/produk')
            ->assertOk()
            ->assertSee($product->nama)
            ->assertSee('Rp 23.000')            // harga dasar & harga jual Original
            ->assertSeeInOrder(['Oreo', 'Rp 25.000'])
            ->assertSee('>15</td>', false);     // total stok 10 + 5
    }

    // ── Create ───────────────────────────────────────────────

    public function test_admin_menambah_produk_beserta_varian_dan_foto(): void
    {
        $this->actingAs($this->admin)->post('/internal/produk', $this->dataBaru())
            ->assertRedirect('/internal/produk')
            ->assertSessionHas('status', 'Produk "Mini Cheesecake" ditambahkan dengan 2 varian.');

        $product = Product::with('variants')->sole();
        $this->assertSame(15000, $product->harga);
        $this->assertStringStartsWith('products/', $product->gambar);
        Storage::disk('public')->assertExists($product->gambar);

        $lotus = $product->variants->firstWhere('nama_varian', 'Lotus');
        $keju = $product->variants->firstWhere('nama_varian', 'Keju');
        $this->assertSame(18000, $lotus->harga_akhir);
        $this->assertSame(12, $lotus->stok);
        Storage::disk('public')->assertExists($lotus->gambar);
        $this->assertNull($keju->gambar);               // memakai foto produk
        $this->assertSame($product->gambar_url, $keju->gambar_url);

        // Langsung tampil di katalog pelanggan.
        $this->get('/')->assertOk()->assertSee('Lotus')->assertSee('Rp 18.000');
    }

    public function test_tambah_produk_memvalidasi_input(): void
    {
        $this->actingAs($this->admin)->post('/internal/produk', ['nama' => '', 'harga' => -1, 'variants' => []])
            ->assertSessionHasErrors(['nama', 'harga', 'gambar', 'variants']);

        $this->actingAs($this->admin)->post('/internal/produk', $this->dataBaru([
            'gambar' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
            'variants' => [
                ['nama_varian' => 'Lotus', 'harga_tambahan' => 0, 'stok' => 1],
                ['nama_varian' => 'lotus', 'harga_tambahan' => 0, 'stok' => -2],
                ['id' => 1, 'nama_varian' => 'Keju', 'harga_tambahan' => 0, 'stok' => 1],
            ],
        ]))->assertSessionHasErrors([
            'gambar' => 'Foto produk harus berupa gambar.',
            'variants.1.nama_varian' => 'Varian ke-2: nama varian tidak boleh sama dengan varian lain.',
            'variants.1.stok' => 'Varian ke-2: stok tidak boleh negatif.',
            'variants.2.id' => 'Varian baru tidak boleh membawa ID.',
        ]);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    // ── Update ───────────────────────────────────────────────

    public function test_admin_mengubah_produk_menambah_dan_menghapus_varian(): void
    {
        $product = $this->produkAwal();
        [$original, $oreo] = [$product->variants[0], $product->variants[1]];

        $this->actingAs($this->admin)->put("/internal/produk/{$product->id}", $this->dataUbah($product, [
            ['id' => $original->id, 'nama_varian' => 'Original', 'harga_tambahan' => 0, 'stok' => 20],
            ['nama_varian' => 'Matcha', 'harga_tambahan' => 4000, 'stok' => 7],
            // Oreo tidak dikirim -> dinonaktifkan
        ], ['nama' => 'Basque Burnt Cheesecake (Slice)', 'harga' => 24000]))
            ->assertRedirect('/internal/produk')
            ->assertSessionHas('status');

        $product->refresh()->load('variants');
        $this->assertSame('Basque Burnt Cheesecake (Slice)', $product->nama);
        $this->assertSame(['Original', 'Matcha'], $product->variants->pluck('nama_varian')->all());
        $this->assertSame(20, $original->fresh()->stok);
        $this->assertSame(24000, $original->fresh()->harga_akhir);
        $this->assertSame(28000, $product->variants[1]->harga_akhir);
        $this->assertSoftDeleted($oreo);
    }

    public function test_ganti_foto_menghapus_file_lama_hanya_jika_tidak_dipakai_lagi(): void
    {
        $product = $this->produkAwal();
        $original = $product->variants[0];
        $variants = fn (array $tambahan = []) => [
            array_merge(['id' => $original->id, 'nama_varian' => 'Original', 'harga_tambahan' => 0, 'stok' => 10], $tambahan),
            ['id' => $product->variants[1]->id, 'nama_varian' => 'Oreo', 'harga_tambahan' => 2000, 'stok' => 5],
        ];

        // Ganti foto produk: file lama masih dipakai varian Original -> tidak dihapus.
        $this->actingAs($this->admin)->put("/internal/produk/{$product->id}", $this->dataUbah($product, $variants(), ['gambar' => $this->foto('baru.jpg')]));
        $this->assertNotSame('products/lama.jpg', $product->fresh()->gambar);
        Storage::disk('public')->assertExists('products/lama.jpg');

        // Ganti foto varian Original: kini tidak ada yang memakai file lama -> dihapus.
        $this->actingAs($this->admin)->put("/internal/produk/{$product->id}", $this->dataUbah($product->fresh(), $variants(['gambar' => $this->foto('original.jpg')])));
        Storage::disk('public')->assertMissing('products/lama.jpg');
        Storage::disk('public')->assertExists($original->fresh()->gambar);
    }

    public function test_ubah_produk_menolak_id_varian_milik_produk_lain(): void
    {
        $product = $this->produkAwal();
        $lain = Product::create(['nama' => 'Lain', 'harga' => 1000]);
        $varianLain = $lain->variants()->create(['nama_varian' => 'X', 'stok' => 1, 'harga_tambahan' => 0]);

        $this->actingAs($this->admin)->put("/internal/produk/{$product->id}", $this->dataUbah($product, [
            ['id' => $varianLain->id, 'nama_varian' => 'Bajakan', 'harga_tambahan' => 0, 'stok' => 99],
        ]))->assertSessionHasErrors(['variants.0.id' => 'Varian ke-1 tidak ditemukan pada produk ini.']);

        $this->assertSame('X', $varianLain->fresh()->nama_varian);
        $this->assertCount(2, $product->variants()->get());
    }

    // ── Delete (soft delete) ─────────────────────────────────

    public function test_hapus_produk_memakai_soft_delete_dan_hilang_dari_katalog(): void
    {
        $product = $this->produkAwal();
        $oreo = $product->variants[1];

        // Riwayat pesanan lama yang memakai varian ini.
        $order = Order::create([
            'kode_pesanan' => 'BNB-2026-HIST01', 'nama_pelanggan' => 'Pelanggan Lama', 'nomor_hp' => '081200000001',
            'total_harga' => 25000, 'status' => OrderStatus::Selesai, 'metode_pengiriman' => ShippingMethod::AmbilSendiri,
            'metode_pembayaran' => PaymentMethod::Cash, 'tanggal_ambil' => now()->toDateString(),
        ]);
        $item = $order->items()->create(['product_variant_id' => $oreo->id, 'jumlah' => 1, 'harga_satuan' => 25000, 'subtotal' => 25000]);

        $this->get('/')->assertSee('Oreo');

        $this->actingAs($this->admin)->delete("/internal/produk/{$product->id}")
            ->assertRedirect('/internal/produk')
            ->assertSessionHas('status', 'Produk "Basque Burnt Cheesecake" dihapus dan tidak lagi tampil di katalog.');

        // Baris tetap ada di database dengan deleted_at terisi.
        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($oreo);
        $this->assertSoftDeleted($product->variants[0]);
        $this->assertDatabaseCount('products', 1);
        $this->assertNotNull(Product::withTrashed()->find($product->id)->deleted_at);

        // Hilang dari katalog, tidak bisa dimasukkan ke keranjang, tidak bisa diubah lagi.
        $this->get('/')->assertOk()->assertDontSee('Oreo')->assertSee('Belum ada produk yang dijual.');
        $this->postJson('/cart/add', ['variant_id' => $oreo->id])->assertJsonValidationErrors('variant_id');
        $this->actingAs($this->admin)->get("/internal/produk/{$product->id}/edit")->assertNotFound();
        $this->actingAs($this->admin)->get('/internal/produk')->assertDontSee('Basque Burnt Cheesecake');

        // Riwayat pesanan tetap bisa membaca varian yang dihapus; file foto tidak dihapus.
        $this->assertSame('Oreo', $item->fresh()->productVariant->nama_varian);
        Storage::disk('public')->assertExists('products/lama.jpg');
    }
}
