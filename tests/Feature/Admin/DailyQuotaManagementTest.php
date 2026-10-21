<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\DailyQuota;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyQuotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 20 Oktober 2026.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->koordinator = User::factory()->role(UserRole::Koordinator)->create();
        $this->staf = User::factory()->role(UserRole::Staf)->create();
    }

    private function kuota(string $tanggal, int $maks, int $terpakai = 0): DailyQuota
    {
        return DailyQuota::create(['tanggal' => $tanggal, 'kuota_maksimal' => $maks, 'kuota_terpakai' => $terpakai]);
    }

    // ── Hak akses ────────────────────────────────────────────

    public function test_hak_akses_kuota_per_role(): void
    {
        $quota = $this->kuota('2026-10-25', 30);

        $this->get('/internal/kuota')->assertRedirect('/login');

        // Staf: tidak ada akses sama sekali.
        $this->actingAs($this->staf)->get('/internal/kuota')->assertForbidden();
        $this->actingAs($this->staf)->post('/internal/kuota', ['tanggal' => '2026-10-26', 'kuota_maksimal' => 10])->assertForbidden();
        $this->actingAs($this->staf)->putJson("/internal/kuota/{$quota->id}", ['kuota_maksimal' => 5])->assertForbidden();
        $this->actingAs($this->staf)->deleteJson("/internal/kuota/{$quota->id}")->assertForbidden();

        // Koordinator: lihat, atur, ubah — tidak bisa hapus.
        $this->actingAs($this->koordinator)->get('/internal/kuota')->assertOk()->assertDontSee('js-quota-delete', false);
        $this->actingAs($this->koordinator)->post('/internal/kuota', ['tanggal' => '2026-10-26', 'kuota_maksimal' => 10])->assertRedirect();
        $this->actingAs($this->koordinator)->putJson("/internal/kuota/{$quota->id}", ['kuota_maksimal' => 40])->assertOk();
        $this->actingAs($this->koordinator)->deleteJson("/internal/kuota/{$quota->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya Admin yang dapat menghapus kuota.');

        // Admin: semua, termasuk hapus.
        $this->actingAs($this->admin)->get('/internal/kuota')->assertOk()->assertSee('js-quota-delete', false);
        $this->actingAs($this->admin)->deleteJson("/internal/kuota/{$quota->id}")->assertOk();

        $this->assertDatabaseMissing('daily_quotas', ['id' => $quota->id]);
        $this->assertDatabaseHas('daily_quotas', ['kuota_maksimal' => 10]);
    }

    public function test_navbar_kelola_sesuai_role(): void
    {
        $this->actingAs($this->admin)->get('/internal/pesanan')->assertSee('/internal/produk')->assertSee('/internal/kuota');
        $this->actingAs($this->koordinator)->get('/internal/pesanan')->assertDontSee('/internal/produk')->assertSee('/internal/kuota');
        $this->actingAs($this->staf)->get('/internal/pesanan')->assertDontSee('/internal/produk')->assertDontSee('/internal/kuota');
    }

    // ── Read ─────────────────────────────────────────────────

    public function test_daftar_kuota_menampilkan_sisa_dan_menyembunyikan_tanggal_lewat(): void
    {
        $this->kuota('2026-10-22', 40, 15);
        $this->kuota('2026-10-23', 20, 20);
        $this->kuota('2026-10-10', 30, 30); // sudah lewat

        $this->actingAs($this->admin)->get('/internal/kuota')
            ->assertOk()
            ->assertSee('kuota default <strong>30 pcs</strong>', false)
            ->assertSeeInOrder(['Kamis, 22 Oktober 2026', 'Sisa 25', 'Jumat, 23 Oktober 2026', 'Penuh'])
            ->assertDontSee('10 Oktober 2026');

        $this->actingAs($this->admin)->get('/internal/kuota?tampil=semua')
            ->assertOk()
            ->assertSee('Sabtu, 10 Oktober 2026')
            ->assertSee('Sudah lewat');
    }

    // ── Create ───────────────────────────────────────────────

    public function test_atur_kuota_tanggal_baru(): void
    {
        $this->actingAs($this->admin)->post('/internal/kuota', ['tanggal' => '2026-10-25', 'kuota_maksimal' => 50])
            ->assertRedirect('/internal/kuota')
            ->assertSessionHas('status', 'Kuota Minggu, 25 Oktober 2026 diatur: 50 pcs.');

        $quota = DailyQuota::sole();
        $this->assertSame('2026-10-25', $quota->tanggal->toDateString());
        $this->assertSame(50, $quota->kuota_maksimal);
        $this->assertSame(0, $quota->kuota_terpakai);
    }

    public function test_atur_kuota_memvalidasi_input(): void
    {
        $this->kuota('2026-10-25', 30);

        $this->actingAs($this->admin)->post('/internal/kuota', ['tanggal' => '2026-10-19', 'kuota_maksimal' => 10])
            ->assertSessionHasErrors(['tanggal' => 'Kuota hanya bisa diatur untuk hari ini atau tanggal mendatang.']);
        $this->actingAs($this->admin)->post('/internal/kuota', ['tanggal' => '2026-10-25', 'kuota_maksimal' => 10])
            ->assertSessionHasErrors(['tanggal' => 'Kuota untuk tanggal ini sudah diatur. Ubah nilainya di tabel.']);
        $this->actingAs($this->admin)->post('/internal/kuota', ['tanggal' => '2026-10-26', 'kuota_maksimal' => 0])
            ->assertSessionHasErrors(['kuota_maksimal' => 'Kuota minimal 1 pcs.']);

        $this->assertDatabaseCount('daily_quotas', 1);
    }

    // ── Update (AJAX) ────────────────────────────────────────

    public function test_ubah_kuota_via_ajax(): void
    {
        $quota = $this->kuota('2026-10-22', 40, 15);

        $this->actingAs($this->admin)->putJson("/internal/kuota/{$quota->id}", ['kuota_maksimal' => 60])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Kuota Kamis, 22 Oktober 2026 diubah menjadi 60 pcs.')
            ->assertSee('Sisa 45');

        $this->assertSame(60, $quota->fresh()->kuota_maksimal);
    }

    public function test_kuota_tidak_bisa_diturunkan_di_bawah_yang_sudah_dipesan(): void
    {
        $quota = $this->kuota('2026-10-22', 40, 15);

        $this->actingAs($this->admin)->putJson("/internal/kuota/{$quota->id}", ['kuota_maksimal' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kuota_maksimal' => 'Kuota tidak boleh kurang dari 15 pcs (jumlah yang sudah dipesan untuk tanggal ini).']);

        $this->actingAs($this->admin)->putJson("/internal/kuota/{$quota->id}", ['kuota_maksimal' => 15])->assertOk();
        $this->assertSame(15, $quota->fresh()->kuota_maksimal);
    }

    // ── Delete ───────────────────────────────────────────────

    public function test_hapus_kuota_hanya_jika_belum_ada_pesanan(): void
    {
        $kosong = $this->kuota('2026-10-24', 30);
        $terisi = $this->kuota('2026-10-22', 40, 3);

        $this->actingAs($this->admin)->deleteJson("/internal/kuota/{$kosong->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Kuota Sabtu, 24 Oktober 2026 dihapus. Tanggal ini kembali memakai kuota default (30 pcs).');
        $this->assertDatabaseMissing('daily_quotas', ['id' => $kosong->id]);

        $this->actingAs($this->admin)->deleteJson("/internal/kuota/{$terisi->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kuota' => 'Kuota tidak bisa dihapus karena sudah ada 3 pcs dipesan untuk tanggal ini. Ubah nilainya saja.']);
        $this->assertDatabaseHas('daily_quotas', ['id' => $terisi->id]);
    }

    // ── Integrasi dengan checkout ────────────────────────────

    public function test_kuota_yang_diatur_admin_membatasi_checkout_pelanggan(): void
    {
        $product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'harga' => 23000]);
        $variant = $product->variants()->create(['nama_varian' => 'Original', 'stok' => 50, 'harga_tambahan' => 0]);

        $this->actingAs($this->admin)->post('/internal/kuota', ['tanggal' => '2026-10-22', 'kuota_maksimal' => 2]);
        auth()->logout();

        $this->postJson('/cart/add', ['variant_id' => $variant->id, 'jumlah' => 3])->assertOk();
        $this->from('/checkout')->post('/checkout', [
            'nama_pelanggan' => 'Sinta', 'nomor_hp' => '081234567890', 'metode_pengiriman' => 'ambil_sendiri',
            'metode_pembayaran' => 'cash', 'tanggal_ambil' => '2026-10-22',
        ])->assertSessionHasErrors('tanggal_ambil');

        $this->assertStringContainsString('tinggal 2 pcs', session('errors')->first('tanggal_ambil'));
        $this->assertDatabaseCount('orders', 0);
    }
}
