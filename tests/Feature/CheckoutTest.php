<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DailyQuota;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $original;

    private ProductVariant $oreo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local'); // bukti bayar disimpan di disk privat
        // Selasa, 20 Okt 2026 10:00 WIB -> pre-order valid 21 Okt s.d. 3 Nov, Minggu 25 Okt tutup.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));

        $product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'harga' => 23000]);
        $this->original = $product->variants()->create(['nama_varian' => 'Original', 'stok' => 10, 'harga_tambahan' => 0]);
        $this->oreo = $product->variants()->create(['nama_varian' => 'Oreo', 'stok' => 5, 'harga_tambahan' => 2000]);
    }

    /**
     * Default: 2 Original + 1 Oreo = Rp 71.000 (3 pcs).
     *
     * @param  array<int, int>|null  $items  variant_id => jumlah
     */
    private function isiKeranjang(?array $items = null): void
    {
        $items ??= [$this->original->id => 2, $this->oreo->id => 1];

        foreach ($items as $variantId => $jumlah) {
            $this->postJson('/cart/add', ['variant_id' => $variantId, 'jumlah' => $jumlah])->assertOk();
        }
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function checkout(array $override = []): TestResponse
    {
        return $this->from('/checkout')->post('/checkout', array_merge([
            'nama_pelanggan' => 'Sinta Wijaya',
            'nomor_hp' => '0812-3456-7890',
            'metode_pengiriman' => 'ambil_sendiri',
            'metode_pembayaran' => 'transfer',
            'tanggal_ambil' => '2026-10-22',
        ], $override));
    }

    private function buktiValid(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg', 600, 900)->size(400);
    }

    // ── Form checkout ────────────────────────────────────────

    public function test_checkout_dengan_keranjang_kosong_dialihkan_ke_keranjang(): void
    {
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('keranjang');

        $this->checkout()->assertRedirect('/checkout')->assertSessionHasErrors('keranjang');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_halaman_checkout_menampilkan_form_dan_ringkasan(): void
    {
        $this->isiKeranjang();

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Data Pemesan')
            ->assertSee('Nomor HP / WhatsApp')
            ->assertSee('Ambil sendiri di toko')
            ->assertSee('Diantar (ojek online)')
            ->assertSee('Transfer Bank')
            ->assertSee('QRIS')
            ->assertSee('Tunai (bayar saat ambil/diantar)')
            ->assertSee('min="2026-10-21"', false)
            ->assertSee('max="2026-11-03"', false)
            ->assertSeeInOrder(['Original', 'Rp 46.000', 'Oreo', 'Rp 25.000', 'Total', 'Rp 71.000']);
    }

    // ── Simpan pesanan ───────────────────────────────────────

    public function test_checkout_berhasil_membuat_pesanan_mengurangi_stok_dan_kuota(): void
    {
        $this->isiKeranjang();

        $response = $this->checkout();

        $order = Order::with('items', 'statusLogs')->sole();
        $response->assertRedirect("/checkout/{$order->kode_pesanan}/pembayaran");

        $this->assertMatchesRegularExpression('/^BNB-2026-[A-HJ-KMNP-Z2-9]{6}$/', $order->kode_pesanan);
        $this->assertSame('Sinta Wijaya', $order->nama_pelanggan);
        $this->assertSame('081234567890', $order->nomor_hp); // dinormalisasi
        $this->assertNull($order->alamat);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertSame(PaymentMethod::Transfer, $order->metode_pembayaran);
        $this->assertDatabaseCount('payments', 0); // baris pembayaran dibuat saat bukti diunggah
        $this->assertSame(71000, $order->total_harga);
        $this->assertSame('2026-10-22', $order->tanggal_ambil->toDateString());

        // Item menyimpan snapshot harga.
        $this->assertEqualsCanonicalizing(
            [[$this->original->id, 2, 23000, 46000], [$this->oreo->id, 1, 25000, 25000]],
            $order->items->map(fn ($i) => [$i->product_variant_id, $i->jumlah, $i->harga_satuan, $i->subtotal])->all(),
        );

        // Stok & kuota berkurang.
        $this->assertSame(8, $this->original->fresh()->stok);
        $this->assertSame(4, $this->oreo->fresh()->stok);
        $kuota = DailyQuota::where('tanggal', '2026-10-22')->sole();
        $this->assertSame(30, $kuota->kuota_maksimal); // kuota default config
        $this->assertSame(3, $kuota->kuota_terpakai);

        // Keranjang dikosongkan, pesanan tercatat sebagai milik session ini, log status dibuat.
        $this->assertNull(session('cart'));
        $this->assertContains($order->kode_pesanan, session(OrderAccess::SESSION_KEY));
        $this->assertCount(1, $order->statusLogs);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->statusLogs[0]->status_baru);
        $this->assertNull($order->statusLogs[0]->created_by);
    }

    public function test_pesanan_diantar_wajib_alamat(): void
    {
        $this->isiKeranjang();

        $this->checkout(['metode_pengiriman' => 'diantar'])->assertSessionHasErrors('alamat');
        $this->assertDatabaseCount('orders', 0);

        $this->checkout(['metode_pengiriman' => 'diantar', 'alamat' => 'Jl. Kemang Raya No. 10, Jakarta Selatan']);
        $this->assertSame('Jl. Kemang Raya No. 10, Jakarta Selatan', Order::sole()->alamat);
    }

    public function test_ambil_sendiri_tidak_menyimpan_alamat(): void
    {
        $this->isiKeranjang();

        $this->checkout(['alamat' => 'Alamat yang tidak dipakai']);

        $this->assertNull(Order::sole()->alamat);
    }

    public function test_nomor_hp_format_internasional_dinormalisasi(): void
    {
        $this->isiKeranjang();

        $this->checkout(['nomor_hp' => '+62 812 3456 7890']);

        $this->assertSame('081234567890', Order::sole()->nomor_hp);
    }

    public function test_validasi_data_pemesan(): void
    {
        $this->isiKeranjang();

        $this->checkout(['nama_pelanggan' => '', 'nomor_hp' => '12345', 'tanggal_ambil' => '2026-10-20', 'metode_pembayaran' => 'bitcoin'])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['nama_pelanggan', 'nomor_hp', 'tanggal_ambil', 'metode_pembayaran']);

        $this->assertSame('Metode pembayaran tidak valid.', session('errors')->first('metode_pembayaran'));

        // Form dirender ulang (redirect diikuti) dengan input lama yang tidak valid, tanpa error 500.
        $this->followingRedirects()
            ->from('/checkout')
            ->post('/checkout', ['metode_pembayaran' => 'bitcoin'])
            ->assertOk()
            ->assertSee('Metode pembayaran tidak valid.');

        $this->checkout(['metode_pengiriman' => 'drone'])
            ->assertSessionHasErrors(['metode_pengiriman' => 'Metode pengambilan tidak valid.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->original->fresh()->stok);
        $this->assertNotNull(session('cart')); // keranjang tetap ada
    }

    public function test_tanggal_hari_minggu_atau_lebih_dari_14_hari_ditolak(): void
    {
        $this->isiKeranjang();

        $this->checkout(['tanggal_ambil' => '2026-10-25'])->assertSessionHasErrors('tanggal_ambil');
        $this->assertStringContainsString('Toko tutup pada Minggu, 25 Oktober 2026', session('errors')->first('tanggal_ambil'));

        $this->checkout(['tanggal_ambil' => '2026-11-04'])->assertSessionHasErrors('tanggal_ambil');
        $this->checkout(['tanggal_ambil' => '2026-11-03'])->assertSessionHasNoErrors();
    }

    public function test_kuota_harian_tidak_cukup_ditolak(): void
    {
        DailyQuota::create(['tanggal' => '2026-10-22', 'kuota_maksimal' => 2, 'kuota_terpakai' => 0]);
        $this->isiKeranjang(); // 3 pcs

        $this->checkout()->assertSessionHasErrors('tanggal_ambil');
        $this->assertStringContainsString('tinggal 2 pcs', session('errors')->first('tanggal_ambil'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->original->fresh()->stok);
        $this->assertSame(0, DailyQuota::where('tanggal', '2026-10-22')->sole()->kuota_terpakai);
    }

    public function test_kuota_harian_penuh_ditolak(): void
    {
        DailyQuota::create(['tanggal' => '2026-10-22', 'kuota_maksimal' => 20, 'kuota_terpakai' => 20]);
        $this->isiKeranjang();

        $this->checkout()->assertSessionHasErrors('tanggal_ambil');
        $this->assertStringContainsString('sudah penuh', session('errors')->first('tanggal_ambil'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stok_yang_berkurang_setelah_masuk_keranjang_ditolak_saat_checkout(): void
    {
        $this->isiKeranjang([$this->oreo->id => 3]);
        $this->oreo->update(['stok' => 2]); // terjual ke pelanggan lain

        $this->checkout()->assertSessionHasErrors('keranjang');
        $this->assertStringContainsString('Stok Oreo tidak mencukupi (tersisa 2)', session('errors')->first('keranjang'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, (int) DailyQuota::where('tanggal', '2026-10-22')->value('kuota_terpakai'));
    }

    public function test_kode_pesanan_selalu_unik(): void
    {
        $this->isiKeranjang([$this->original->id => 1]);
        $this->checkout();
        $this->isiKeranjang([$this->original->id => 1]);
        $this->checkout();

        $kode = Order::pluck('kode_pesanan');
        $this->assertCount(2, $kode->unique());
    }

    // ── Pembayaran & upload bukti ────────────────────────────

    public function test_halaman_pembayaran_transfer_menampilkan_kode_total_dan_rekening(): void
    {
        $this->isiKeranjang();
        $this->checkout();
        $order = Order::sole();

        $this->get("/checkout/{$order->kode_pesanan}/pembayaran")
            ->assertOk()
            ->assertSee($order->kode_pesanan)
            ->assertSee('Rp 71.000')
            ->assertSee('Bayar via Transfer Bank')
            ->assertSee(config('burnbliss.pembayaran.nomor_rekening'))
            ->assertDontSee('alt="QRIS Burn', false)
            ->assertSee('Kirim Bukti Pembayaran')
            ->assertSee('0812****7890')
            ->assertDontSee('081234567890');
    }

    public function test_halaman_pembayaran_qris_hanya_menampilkan_qris(): void
    {
        $this->isiKeranjang();
        $this->checkout(['metode_pembayaran' => 'qris']);
        $order = Order::sole();

        $this->get("/checkout/{$order->kode_pesanan}/pembayaran")
            ->assertOk()
            ->assertSee('Bayar via QRIS')
            ->assertSee('alt="QRIS Burn', false)
            ->assertDontSee(config('burnbliss.pembayaran.nomor_rekening'))
            ->assertSee('Kirim Bukti Pembayaran');
    }

    public function test_upload_bukti_menyimpan_pembayaran_dan_mengubah_status(): void
    {
        $this->isiKeranjang();
        $this->checkout(['metode_pembayaran' => 'qris']);
        $order = Order::sole();

        $this->post("/checkout/{$order->kode_pesanan}/pembayaran", ['bukti' => $this->buktiValid()])
            ->assertRedirect("/checkout/{$order->kode_pesanan}/selesai");

        $order->refresh()->load('payments', 'statusLogs');
        $payment = $order->payments->sole();

        $this->assertSame(PaymentStatus::MenungguVerifikasi, $payment->status);
        $this->assertSame(PaymentMethod::Qris, $payment->metode);
        $this->assertSame(71000, $payment->jumlah);
        $this->assertStringStartsWith('bukti/', $payment->bukti_transfer);
        Storage::disk('local')->assertExists($payment->bukti_transfer);
        Storage::disk('public')->assertMissing($payment->bukti_transfer); // tidak ada salinan publik

        $this->assertSame(OrderStatus::MenungguVerifikasi, $order->status);
        $this->assertCount(2, $order->statusLogs);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->statusLogs[1]->status_lama);
        $this->assertSame(OrderStatus::MenungguVerifikasi, $order->statusLogs[1]->status_baru);

        $this->get("/checkout/{$order->kode_pesanan}/selesai")
            ->assertOk()
            ->assertSee('Terima kasih, Sinta Wijaya!')
            ->assertSee($order->kode_pesanan)
            ->assertSee('Menunggu Verifikasi');
    }

    public function test_upload_bukti_divalidasi(): void
    {
        $this->isiKeranjang();
        $this->checkout();
        $url = '/checkout/'.Order::sole()->kode_pesanan.'/pembayaran';

        $this->from($url)->post($url, [])->assertSessionHasErrors('bukti');
        $this->from($url)->post($url, ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('bukti');
        $this->from($url)->post($url, ['bukti' => UploadedFile::fake()->image('besar.png')->size(2049)])
            ->assertSessionHasErrors(['bukti' => 'Ukuran bukti pembayaran maksimal 2 MB.']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('bukti'));
        $this->assertSame(OrderStatus::MenungguPembayaran, Order::sole()->status);
    }

    public function test_bukti_tidak_bisa_diunggah_dua_kali(): void
    {
        $this->isiKeranjang();
        $this->checkout();
        $url = '/checkout/'.Order::sole()->kode_pesanan.'/pembayaran';

        $this->post($url, ['bukti' => $this->buktiValid()]);
        $this->from($url)->post($url, ['bukti' => $this->buktiValid()])
            ->assertSessionHasErrors('bukti');

        $this->assertDatabaseCount('payments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('bukti'));
    }

    public function test_halaman_selesai_sebelum_upload_dialihkan_ke_pembayaran(): void
    {
        $this->isiKeranjang();
        $this->checkout();
        $kode = Order::sole()->kode_pesanan;

        $this->get("/checkout/{$kode}/selesai")->assertRedirect("/checkout/{$kode}/pembayaran");
    }

    // ── Pembayaran tunai (cash) ──────────────────────────────

    public function test_checkout_tunai_langsung_dikonfirmasi_tanpa_upload_bukti(): void
    {
        $this->isiKeranjang();

        $response = $this->checkout(['metode_pembayaran' => 'cash']);

        $order = Order::with('payments', 'statusLogs')->sole();
        // Langsung ke halaman selesai, tidak melewati halaman upload bukti.
        $response->assertRedirect("/checkout/{$order->kode_pesanan}/selesai");

        // Pesanan masuk antrean produksi; uangnya dicatat belum diterima.
        $this->assertSame(OrderStatus::Dikonfirmasi, $order->status);
        $this->assertSame(PaymentMethod::Cash, $order->metode_pembayaran);
        $payment = $order->payments->sole();
        $this->assertSame(PaymentMethod::Cash, $payment->metode);
        $this->assertSame(PaymentStatus::MenungguPembayaran, $payment->status);
        $this->assertSame(71000, $payment->jumlah);
        $this->assertNull($payment->bukti_transfer);

        $this->assertNull($order->statusLogs->sole()->status_lama);
        $this->assertSame(OrderStatus::Dikonfirmasi, $order->statusLogs->sole()->status_baru);

        // Stok & kuota tetap dikurangi seperti pesanan lain.
        $this->assertSame(8, $this->original->fresh()->stok);
        $this->assertSame(3, DailyQuota::where('tanggal', '2026-10-22')->sole()->kuota_terpakai);
        $this->assertNull(session('cart'));

        $this->get("/checkout/{$order->kode_pesanan}/selesai")
            ->assertOk()
            ->assertSee('Terima kasih, Sinta Wijaya!')
            ->assertSee('Silakan siapkan uang tunai sebesar <strong>Rp 71.000</strong>', false)
            ->assertSee('saat mengambil pesanan di toko')
            ->assertSee('Tunai')
            ->assertDontSee('sedang diverifikasi')
            ->assertDontSee('Kirim Bukti Pembayaran');
    }

    public function test_halaman_pembayaran_tunai_tanpa_rekening_qris_dan_form_upload(): void
    {
        $this->isiKeranjang();
        $this->checkout(['metode_pembayaran' => 'cash', 'metode_pengiriman' => 'diantar', 'alamat' => 'Jl. Kemang Raya No. 10']);
        $order = Order::sole();

        $this->get("/checkout/{$order->kode_pesanan}/pembayaran")
            ->assertOk()
            ->assertSee('Silakan siapkan uang tunai sebesar <strong>Rp 71.000</strong>', false)
            ->assertSee('saat menerima pesanan')
            ->assertDontSee('Kirim Bukti Pembayaran')
            ->assertDontSee(config('burnbliss.pembayaran.nomor_rekening'))
            ->assertDontSee('alt="QRIS Burn', false)
            ->assertDontSee('type="file"', false);
    }

    public function test_pesanan_tunai_menolak_upload_bukti(): void
    {
        $this->isiKeranjang();
        $this->checkout(['metode_pembayaran' => 'cash']);
        $order = Order::sole();
        $url = "/checkout/{$order->kode_pesanan}/pembayaran";

        $this->from($url)->post($url, ['bukti' => $this->buktiValid()])
            ->assertSessionHasErrors(['bukti' => 'Pesanan ini dibayar tunai saat serah terima, tidak perlu mengunggah bukti pembayaran.']);

        $this->assertDatabaseCount('payments', 1); // hanya catatan tunai
        $this->assertEmpty(Storage::disk('local')->allFiles('bukti'));
        $this->assertSame(OrderStatus::Dikonfirmasi, $order->fresh()->status);
    }

    // ── Hak akses pesanan tamu ───────────────────────────────

    public function test_pesanan_hanya_bisa_dibuka_session_pemesan_atau_dengan_nomor_hp(): void
    {
        $this->isiKeranjang();
        $this->checkout();
        $kode = Order::sole()->kode_pesanan;

        $this->flushSession(); // seolah dibuka dari perangkat lain

        $this->get("/checkout/{$kode}/pembayaran")->assertRedirect("/checkout/{$kode}/akses");
        $this->post("/checkout/{$kode}/pembayaran", ['bukti' => $this->buktiValid()])
            ->assertRedirect("/checkout/{$kode}/akses");
        $this->assertDatabaseCount('payments', 0);

        $this->get("/checkout/{$kode}/akses")->assertOk()->assertSee('Nomor HP pemesan')->assertDontSee('Sinta');

        $this->from("/checkout/{$kode}/akses")
            ->post("/checkout/{$kode}/akses", ['nomor_hp' => '0899 1111 2222'])
            ->assertSessionHasErrors(['nomor_hp' => 'Nomor HP tidak cocok dengan pesanan ini.']);

        $this->post("/checkout/{$kode}/akses", ['nomor_hp' => '+62 812-3456-7890'])
            ->assertRedirect("/checkout/{$kode}/pembayaran");
        $this->get("/checkout/{$kode}/pembayaran")->assertOk();
    }

    public function test_kode_pesanan_tidak_dikenal_404(): void
    {
        $this->get('/checkout/BNB-2026-TIDAKADA/pembayaran')->assertNotFound();
        $this->get('/checkout/BNB-2026-TIDAKADA/akses')->assertNotFound();
    }

    // ── Rate limit ───────────────────────────────────────────

    public function test_post_checkout_dibatasi_5_kali_per_menit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/checkout', [])->assertRedirect();
        }

        $this->post('/checkout', [])->assertStatus(429);
    }
}
