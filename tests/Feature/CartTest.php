<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'harga' => 23000]);
    }

    private function varian(string $nama = 'Original', int $stok = 10, int $tambahan = 0): ProductVariant
    {
        return $this->product->variants()->create([
            'nama_varian' => $nama,
            'stok' => $stok,
            'harga_tambahan' => $tambahan,
        ]);
    }

    // ── Katalog ──────────────────────────────────────────────

    public function test_katalog_menampilkan_varian_dengan_harga_rupiah(): void
    {
        $this->varian('Original');
        $this->varian('Oreo', 10, 2000);

        $this->get('/')
            ->assertOk()
            ->assertSee('Our Collection of Love')
            ->assertSee('Original')
            ->assertSee('Rp 23.000')
            ->assertSee('Oreo')
            ->assertSee('Rp 25.000')
            ->assertSee('Tambah ke Keranjang')
            ->assertSee('aria-label="Keranjang, 0 item"', false);
    }

    public function test_menu_dialihkan_ke_katalog(): void
    {
        $this->get('/menu')->assertRedirect('/');
    }

    public function test_varian_habis_ditandai_dan_tombolnya_nonaktif(): void
    {
        $this->varian('Original');
        $this->varian('Matcha', 0);

        $this->get('/')->assertOk()->assertSee('Habis')->assertSee('Stok habis');
    }

    // ── Tambah ───────────────────────────────────────────────

    public function test_tambah_mengembalikan_json_dan_menyimpan_session(): void
    {
        $v = $this->varian('Original');

        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 2])
            ->assertOk()
            ->assertJsonStructure([
                'success', 'message', 'cart_count', 'total', 'total_formatted',
                'item' => ['variant_id', 'nama', 'jumlah', 'stok', 'harga_satuan', 'subtotal', 'subtotal_formatted'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Original ditambahkan ke keranjang.',
                'cart_count' => 2,
                'total' => 46000,
                'total_formatted' => 'Rp 46.000',
                'item' => ['variant_id' => $v->id, 'jumlah' => 2, 'subtotal' => 46000],
            ]);

        // Session hanya menyimpan variant_id => jumlah (tanpa harga).
        $this->assertSame([$v->id => 2], session(CartService::SESSION_KEY));
    }

    public function test_jumlah_default_satu_dan_varian_sama_dijumlahkan(): void
    {
        $original = $this->varian('Original');
        $oreo = $this->varian('Oreo', 10, 2000);

        $this->postJson('/cart/add', ['variant_id' => $original->id])->assertJson(['cart_count' => 1]);
        $this->postJson('/cart/add', ['variant_id' => $original->id])->assertJson(['cart_count' => 2, 'item' => ['jumlah' => 2]]);
        $this->postJson('/cart/add', ['variant_id' => $oreo->id])
            ->assertJson(['cart_count' => 3, 'total' => 23000 * 2 + 25000]);

        $this->assertSame([$original->id => 2, $oreo->id => 1], session('cart'));
    }

    public function test_tambah_melebihi_stok_ditolak(): void
    {
        $v = $this->varian('Matcha', 2);

        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jumlah' => 'Stok Matcha hanya tersisa 2.']);

        $this->assertNull(session('cart'));
    }

    public function test_tambah_varian_habis_ditolak(): void
    {
        $v = $this->varian('Matcha', 0);

        $this->postJson('/cart/add', ['variant_id' => $v->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jumlah' => 'Maaf, Matcha sedang habis.']);
    }

    public function test_tambah_varian_tidak_ada_atau_sudah_dihapus_ditolak(): void
    {
        $this->postJson('/cart/add', ['variant_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id']);

        $v = $this->varian('Brownie');
        $v->delete(); // soft delete oleh admin

        $this->postJson('/cart/add', ['variant_id' => $v->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id' => 'Produk tidak ditemukan atau sudah tidak dijual.']);
    }

    public function test_tambah_memvalidasi_input(): void
    {
        $v = $this->varian();

        $this->postJson('/cart/add', [])->assertUnprocessable()->assertJsonValidationErrors(['variant_id']);
        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 0])->assertJsonValidationErrors(['jumlah']);
        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 'abc'])->assertJsonValidationErrors(['jumlah']);
        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 100])->assertJsonValidationErrors(['jumlah']);
    }

    // ── Ubah jumlah ──────────────────────────────────────────

    public function test_update_mengubah_jumlah(): void
    {
        $v = $this->varian('Original');
        $this->postJson('/cart/add', ['variant_id' => $v->id]);

        $this->postJson('/cart/update', ['variant_id' => $v->id, 'jumlah' => 4])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'cart_count' => 4,
                'total_formatted' => 'Rp 92.000',
                'item' => ['jumlah' => 4, 'subtotal_formatted' => 'Rp 92.000'],
            ]);

        $this->assertSame([$v->id => 4], session('cart'));
    }

    public function test_update_ditolak_jika_melebihi_stok_atau_item_belum_ada(): void
    {
        $v = $this->varian('Original', 3);
        $lain = $this->varian('Oreo');

        $this->postJson('/cart/add', ['variant_id' => $v->id]);

        $this->postJson('/cart/update', ['variant_id' => $v->id, 'jumlah' => 4])
            ->assertJsonValidationErrors(['jumlah' => 'Stok Original hanya tersisa 3.']);
        $this->postJson('/cart/update', ['variant_id' => $lain->id, 'jumlah' => 1])
            ->assertJsonValidationErrors(['variant_id' => 'Item tersebut tidak ada di keranjang.']);
        $this->postJson('/cart/update', ['variant_id' => $v->id])
            ->assertJsonValidationErrors(['jumlah']);

        $this->assertSame([$v->id => 1], session('cart'));
    }

    // ── Hapus ────────────────────────────────────────────────

    public function test_remove_menghapus_item(): void
    {
        $original = $this->varian('Original');
        $oreo = $this->varian('Oreo', 10, 2000);
        $this->postJson('/cart/add', ['variant_id' => $original->id, 'jumlah' => 2]);
        $this->postJson('/cart/add', ['variant_id' => $oreo->id]);

        $this->postJson('/cart/remove', ['variant_id' => $original->id])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Item dihapus dari keranjang.',
                'cart_count' => 1,
                'total' => 25000,
                'item' => null,
            ]);

        $this->assertSame([$oreo->id => 1], session('cart'));

        $this->postJson('/cart/remove', ['variant_id' => $original->id])
            ->assertJsonValidationErrors(['variant_id']);
    }

    // ── Halaman keranjang ────────────────────────────────────

    public function test_halaman_keranjang_menampilkan_item_dan_total(): void
    {
        $original = $this->varian('Original');
        $oreo = $this->varian('Oreo', 10, 2000);
        $this->postJson('/cart/add', ['variant_id' => $original->id, 'jumlah' => 2]);
        $this->postJson('/cart/add', ['variant_id' => $oreo->id]);

        $this->get('/cart')
            ->assertOk()
            ->assertSeeInOrder(['Original', 'Rp 46.000', 'Oreo', 'Rp 25.000', 'Total', 'Rp 71.000'])
            ->assertSee('Lanjut ke Checkout')
            ->assertSee('aria-label="Keranjang, 3 item"', false);
    }

    public function test_halaman_keranjang_kosong(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Keranjang Anda masih kosong');
    }

    public function test_harga_selalu_dibaca_dari_database(): void
    {
        $v = $this->varian('Original');
        $this->postJson('/cart/add', ['variant_id' => $v->id, 'jumlah' => 2]);

        $this->product->update(['harga' => 30000]);

        $this->get('/cart')->assertOk()->assertSee('Rp 60.000');
    }

    public function test_varian_yang_dihapus_admin_dibuang_dari_keranjang(): void
    {
        $original = $this->varian('Original');
        $oreo = $this->varian('Oreo', 10, 2000);
        $this->postJson('/cart/add', ['variant_id' => $original->id]);
        $this->postJson('/cart/add', ['variant_id' => $oreo->id]);

        $oreo->delete();

        $this->get('/cart')->assertOk()->assertDontSee('Oreo')->assertSee('Rp 23.000');
        $this->assertSame([$original->id => 1], session('cart'));
    }

    // ── Fallback tanpa JavaScript ────────────────────────────

    public function test_form_biasa_tanpa_ajax_redirect_dengan_flash(): void
    {
        $v = $this->varian('Original', 1);

        $this->from('/')
            ->post('/cart/add', ['variant_id' => $v->id])
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Original ditambahkan ke keranjang.');

        $this->from('/')
            ->post('/cart/add', ['variant_id' => $v->id])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['jumlah']);

        $this->assertSame([$v->id => 1], session('cart'));
    }
}
