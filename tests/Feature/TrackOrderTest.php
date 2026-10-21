<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackOrderTest extends TestCase
{
    use RefreshDatabase;

    private int $urutan = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00:00'));
    }

    /**
     * Buat pesanan lalu jalankan riwayat status satu per satu (tiap langkah +1 jam).
     *
     * @param  list<OrderStatus>  $riwayat  status yang dilalui berurutan (log pertama: null -> status pertama)
     */
    private function pesanan(array $riwayat, PaymentMethod $metode = PaymentMethod::Transfer, ?PaymentStatus $bayar = null, ?string $catatan = null): Order
    {
        $this->urutan++;
        $petugas = User::factory()->role(UserRole::Admin)->create(['name' => 'Petugas Rahasia']);
        $variant = Product::firstOrCreate(['nama' => 'Basque Burnt Cheesecake'], ['harga' => 23000])
            ->variants()->firstOrCreate(['nama_varian' => 'Original'], ['stok' => 50, 'harga_tambahan' => 0]);

        $order = Order::create([
            'kode_pesanan' => sprintf('BNB-2026-TRK%03d', $this->urutan),
            'nama_pelanggan' => 'Sinta Wijaya',
            'nomor_hp' => '081234567890',
            'total_harga' => 46000,
            'status' => $riwayat[0],
            'metode_pengiriman' => ShippingMethod::AmbilSendiri,
            'metode_pembayaran' => $metode,
            'tanggal_ambil' => '2026-10-23',
        ]);
        $order->items()->create(['product_variant_id' => $variant->id, 'jumlah' => 2, 'harga_satuan' => 23000, 'subtotal' => 46000]);

        $sebelumnya = null;
        foreach ($riwayat as $status) {
            $order->statusLogs()->create([
                'status_lama' => $sebelumnya,
                'status_baru' => $status,
                'keterangan' => 'Catatan internal yang tidak boleh bocor.',
                'created_by' => $sebelumnya ? $petugas->id : null,
            ]);
            $order->update(['status' => $status]);
            $sebelumnya = $status;
            $this->travel(1)->hours();
        }

        if ($bayar) {
            $order->payments()->create(['metode' => $metode, 'jumlah' => 46000, 'status' => $bayar, 'catatan' => $catatan]);
        }

        return $order->fresh();
    }

    private function lacak(string $kode, string $hp = '081234567890')
    {
        return $this->postJson('/lacak/cari', ['kode' => $kode, 'nomor_hp' => $hp]);
    }

    // ── Halaman ──────────────────────────────────────────────

    public function test_halaman_lacak_dan_link_di_navbar_serta_footer(): void
    {
        $this->get('/lacak')->assertOk()->assertSee('Di mana cheesecake saya?')->assertSee('Kode pesanan');
        $this->get('/lacak?kode=bnb-2026-abc123')->assertOk()->assertSee('value="BNB-2026-ABC123"', false);

        $katalog = $this->get('/')->assertOk();
        $this->assertSame(2, substr_count($katalog->getContent(), 'href="'.route('track.index').'"')); // navbar + footer
    }

    // ── Sukses ───────────────────────────────────────────────

    public function test_lacak_dengan_data_benar_menampilkan_pesanan_dan_timeline(): void
    {
        $order = $this->pesanan(
            [OrderStatus::MenungguPembayaran, OrderStatus::MenungguVerifikasi, OrderStatus::Dikonfirmasi],
            bayar: PaymentStatus::Diterima,
        );

        // Kode huruf kecil & format HP internasional tetap cocok.
        $res = $this->lacak(strtolower($order->kode_pesanan), '+62 812-3456-7890')
            ->assertOk()
            ->assertJsonPath('order.kode', $order->kode_pesanan)
            ->assertJsonPath('order.nama', 'Sinta Wijaya')
            ->assertJsonPath('order.nomor_hp', '0812****7890')
            ->assertJsonPath('order.total_formatted', 'Rp 46.000')
            ->assertJsonPath('order.status', 'dikonfirmasi')
            ->assertJsonPath('order.status_label', 'Dikonfirmasi')
            ->assertJsonPath('order.dibuat', '20 Okt 2026, 09.00')
            ->assertJsonPath('order.items.0.nama', 'Original');

        $this->assertSame(
            ['menunggu_pembayaran', 'menunggu_verifikasi', 'dikonfirmasi', 'diproses', 'siap', 'selesai'],
            array_column($res->json('timeline'), 'status'),
        );
        $this->assertSame(['done', 'done', 'current', 'upcoming', 'upcoming', 'upcoming'], array_column($res->json('timeline'), 'state'));
        $this->assertSame(['20 Okt 2026, 09.00', '20 Okt 2026, 10.00', '20 Okt 2026, 11.00', null, null, null], array_column($res->json('timeline'), 'waktu'));

        // Data internal tidak ikut terkirim ke publik.
        $this->assertStringNotContainsString('Petugas Rahasia', $res->getContent());
        $this->assertStringNotContainsString('Catatan internal', $res->getContent());
        $this->assertStringNotContainsString('081234567890', $res->getContent());
    }

    public function test_pesanan_tunai_dan_selesai_menampilkan_timeline_yang_sesuai(): void
    {
        $tunai = $this->pesanan([OrderStatus::Dikonfirmasi, OrderStatus::Diproses, OrderStatus::Siap, OrderStatus::Selesai], PaymentMethod::Cash, PaymentStatus::Diterima);

        $res = $this->lacak($tunai->kode_pesanan)->assertOk();

        // Tunai tidak punya tahap pembayaran/verifikasi; pesanan selesai = semua langkah tuntas.
        $this->assertSame(['dikonfirmasi', 'diproses', 'siap', 'selesai'], array_column($res->json('timeline'), 'status'));
        $this->assertSame(['done', 'done', 'done', 'done'], array_column($res->json('timeline'), 'state'));
        $this->assertSame('Pesanan siap diambil di toko.', $res->json('timeline.2.keterangan'));
    }

    public function test_pesanan_dibatalkan_berhenti_di_langkah_terakhir_lalu_dibatalkan(): void
    {
        $batal = $this->pesanan([OrderStatus::MenungguPembayaran, OrderStatus::Dibatalkan]);

        $res = $this->lacak($batal->kode_pesanan)->assertOk();

        $this->assertSame(['menunggu_pembayaran', 'dibatalkan'], array_column($res->json('timeline'), 'status'));
        $this->assertSame(['done', 'cancelled'], array_column($res->json('timeline'), 'state'));
    }

    public function test_bukti_ditolak_menampilkan_alasan_dan_bisa_unggah_ulang(): void
    {
        $order = $this->pesanan(
            [OrderStatus::MenungguPembayaran, OrderStatus::MenungguVerifikasi, OrderStatus::MenungguPembayaran],
            bayar: PaymentStatus::Ditolak,
            catatan: 'Nominal transfer kurang Rp 1.000',
        );

        $this->lacak($order->kode_pesanan)
            ->assertOk()
            ->assertJsonPath('order.catatan_penolakan', 'Nominal transfer kurang Rp 1.000')
            ->assertJsonPath('order.pembayaran_url', route('checkout.pembayaran', $order))
            ->assertJsonPath('timeline.0.state', 'current');

        // Lacak yang berhasil membuktikan kepemilikan -> halaman pembayaran langsung bisa dibuka.
        $this->get("/checkout/{$order->kode_pesanan}/pembayaran")->assertOk()->assertSee('Kirim Bukti Pembayaran');
    }

    // ── Gagal ────────────────────────────────────────────────

    public function test_kode_atau_nomor_hp_salah_mengembalikan_404_dengan_pesan_yang_sama(): void
    {
        $order = $this->pesanan([OrderStatus::MenungguPembayaran]);
        $pesan = 'Pesanan tidak ditemukan. Periksa kembali Kode dan Nomor HP Anda.';

        $this->lacak($order->kode_pesanan, '089999999999')->assertNotFound()->assertExactJson(['message' => $pesan]);
        $this->lacak('BNB-2026-TIDAKADA')->assertNotFound()->assertExactJson(['message' => $pesan]);

        // Gagal lacak tidak memberi akses ke halaman pembayaran.
        $this->get("/checkout/{$order->kode_pesanan}/pembayaran")->assertRedirect("/checkout/{$order->kode_pesanan}/akses");
    }

    public function test_input_kosong_ditolak_422(): void
    {
        $this->postJson('/lacak/cari', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'kode' => 'Masukkan kode pesanan.',
                'nomor_hp' => 'Masukkan nomor HP yang dipakai saat memesan.',
            ]);
    }

    // ── Rate limit ───────────────────────────────────────────

    public function test_lacak_dibatasi_10_percobaan_per_menit(): void
    {
        $order = $this->pesanan([OrderStatus::MenungguPembayaran]);

        for ($i = 0; $i < 10; $i++) {
            $this->lacak('BNB-2026-TEBAK'.$i)->assertNotFound();
        }

        // Percobaan ke-11 diblokir — bahkan dengan data yang benar.
        $this->lacak($order->kode_pesanan)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak percobaan. Tunggu sekitar satu menit lalu coba lagi.');

        // Setelah satu menit, boleh mencoba lagi.
        $this->travel(61)->seconds();
        $this->lacak($order->kode_pesanan)->assertOk();
    }
}
