<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $staf;

    private ProductVariant $variant;

    private int $urutan = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin Uji']);
        $this->koordinator = User::factory()->role(UserRole::Koordinator)->create(['name' => 'Koordinator Uji']);
        $this->staf = User::factory()->role(UserRole::Staf)->create(['name' => 'Staf Uji']);

        $product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'harga' => 23000]);
        $this->variant = $product->variants()->create(['nama_varian' => 'Original', 'stok' => 50, 'harga_tambahan' => 0]);
    }

    /**
     * Buat pesanan langsung dengan status tertentu (tanpa lewat checkout).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function pesanan(
        OrderStatus $status,
        PaymentMethod $metode = PaymentMethod::Transfer,
        ?PaymentStatus $bayar = null,
        array $attrs = [],
    ): Order {
        $this->urutan++;

        $order = Order::create(array_merge([
            'kode_pesanan' => sprintf('BNB-2026-T%05d', $this->urutan),
            'nama_pelanggan' => "Pelanggan {$this->urutan}",
            'nomor_hp' => sprintf('0812%08d', $this->urutan),
            'total_harga' => 46000,
            'status' => $status,
            'metode_pengiriman' => ShippingMethod::AmbilSendiri,
            'metode_pembayaran' => $metode,
            'tanggal_ambil' => '2026-10-22',
        ], $attrs));

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'jumlah' => 2,
            'harga_satuan' => 23000,
            'subtotal' => 46000,
        ]);

        if ($bayar) {
            $path = null;
            if ($metode->butuhBukti()) {
                $path = "bukti/{$order->kode_pesanan}.jpg";
                Storage::disk('local')->put($path, UploadedFile::fake()->image('bukti.jpg', 400, 600)->getContent());
            }

            $order->payments()->create(['metode' => $metode, 'jumlah' => 46000, 'status' => $bayar, 'bukti_transfer' => $path]);
        }

        return $order;
    }

    private function menungguVerifikasi(): Order
    {
        return $this->pesanan(OrderStatus::MenungguVerifikasi, PaymentMethod::Transfer, PaymentStatus::MenungguVerifikasi);
    }

    private function url(Order $order, string $aksi = ''): string
    {
        return "/internal/pesanan/{$order->kode_pesanan}".($aksi ? "/{$aksi}" : '');
    }

    // ── Akses halaman ────────────────────────────────────────

    public function test_tamu_diarahkan_ke_login(): void
    {
        $order = $this->menungguVerifikasi();

        $this->get('/internal/pesanan')->assertRedirect('/login');
        $this->post($this->url($order, 'verifikasi'), ['keputusan' => 'terima'])->assertRedirect('/login');
        $this->get('/internal/pembayaran/'.$order->payments()->value('id').'/bukti')->assertRedirect('/login');
    }

    public function test_semua_role_internal_bisa_membuka_halaman_pesanan(): void
    {
        $this->actingAs($this->admin)->get('/internal/pesanan')->assertOk()->assertSee('Kelola Pesanan');
        $this->actingAs($this->koordinator)->get('/internal/pesanan')->assertOk()->assertSee('Kelola Pesanan');
        $this->actingAs($this->staf)->get('/internal/pesanan')->assertOk()->assertSee('Antrean Produksi');
    }

    public function test_admin_melihat_semua_pesanan_beserta_data_pembayaran(): void
    {
        $verifikasi = $this->menungguVerifikasi();
        $dikonfirmasi = $this->pesanan(OrderStatus::Dikonfirmasi, PaymentMethod::Qris, PaymentStatus::Diterima);
        $selesai = $this->pesanan(OrderStatus::Selesai, PaymentMethod::Cash, PaymentStatus::Diterima);

        $this->actingAs($this->admin)->get('/internal/pesanan')
            ->assertOk()
            ->assertSee([$verifikasi->kode_pesanan, $dikonfirmasi->kode_pesanan, $selesai->kode_pesanan])
            ->assertSee('Rp 46.000')
            ->assertSee('Lihat Bukti')
            ->assertSee(['Transfer Bank', 'QRIS', 'Tunai']);
    }

    public function test_staf_hanya_melihat_antrean_produksi_tanpa_data_keuangan(): void
    {
        $tersembunyi = [
            $this->pesanan(OrderStatus::MenungguPembayaran),
            $this->menungguVerifikasi(),
            $this->pesanan(OrderStatus::Selesai, PaymentMethod::Qris, PaymentStatus::Diterima),
        ];
        $antrean = [
            $this->pesanan(OrderStatus::Dikonfirmasi, PaymentMethod::Qris, PaymentStatus::Diterima),
            $this->pesanan(OrderStatus::Diproses, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran),
            $this->pesanan(OrderStatus::Siap, PaymentMethod::Transfer, PaymentStatus::Diterima),
        ];

        $page = $this->actingAs($this->staf)->get('/internal/pesanan')->assertOk();

        foreach ($antrean as $order) {
            $page->assertSee($order->kode_pesanan);
        }
        foreach ($tersembunyi as $order) {
            $page->assertDontSee($order->kode_pesanan);
        }
        $page->assertDontSee('Rp 46.000')
            ->assertDontSee('Lihat Bukti')
            ->assertDontSee('Transfer Bank')
            ->assertDontSee('id="orderModal"', false);

        // Filter ke status di luar antrean diabaikan (tetap hanya antrean).
        $json = $this->actingAs($this->staf)->getJson('/internal/pesanan?status=menunggu_verifikasi')->assertOk();
        $this->assertStringNotContainsString($tersembunyi[1]->kode_pesanan, $json->json('html'));
        $this->assertSame(0, $json->json('counts.menunggu_verifikasi'));
        $this->assertSame(3, $json->json('counts.semua'));
    }

    // ── Filter & pencarian (AJAX JSON) ───────────────────────

    public function test_filter_status_dan_pencarian_via_ajax(): void
    {
        $a = $this->pesanan(OrderStatus::Dikonfirmasi, attrs: ['nama_pelanggan' => 'Sinta Wijaya', 'nomor_hp' => '081234567890']);
        $b = $this->pesanan(OrderStatus::Dikonfirmasi, attrs: ['nama_pelanggan' => 'Budi Santoso']);
        $c = $this->menungguVerifikasi();

        $res = $this->actingAs($this->admin)->getJson('/internal/pesanan?status=dikonfirmasi')
            ->assertOk()
            ->assertJsonStructure(['html', 'pagination', 'info', 'counts' => ['semua', 'dikonfirmasi', 'menunggu_verifikasi']])
            ->assertJsonPath('counts.semua', 3)
            ->assertJsonPath('counts.dikonfirmasi', 2)
            ->assertJsonPath('info', 'Menampilkan 1–2 dari 2 pesanan');
        $this->assertStringContainsString($a->kode_pesanan, $res->json('html'));
        $this->assertStringNotContainsString($c->kode_pesanan, $res->json('html'));

        $cari = fn (string $q) => $this->actingAs($this->admin)->getJson('/internal/pesanan?q='.urlencode($q))->json('html');

        $this->assertStringContainsString($a->kode_pesanan, $cari('sinta'));
        $this->assertStringNotContainsString($b->kode_pesanan, $cari('sinta'));
        $this->assertStringContainsString($b->kode_pesanan, $cari(substr($b->kode_pesanan, -4)));
        $this->assertStringContainsString($a->kode_pesanan, $cari('+62 812-3456'));
        $this->assertStringContainsString('Tidak ada pesanan yang cocok', $cari('tidak-ada-yang-cocok'));
    }

    // ── Verifikasi pembayaran ────────────────────────────────

    public function test_terima_pembayaran_mengkonfirmasi_pesanan(): void
    {
        $order = $this->menungguVerifikasi();

        $this->actingAs($this->koordinator)
            ->postJson($this->url($order, 'verifikasi'), ['keputusan' => 'terima'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'dikonfirmasi')
            ->assertJsonPath('counts.dikonfirmasi', 1)
            ->assertJsonPath('counts.menunggu_verifikasi', 0)
            ->assertSee('Mulai Produksi');

        $order->refresh()->load('payments', 'statusLogs');
        $payment = $order->payments->sole();

        $this->assertSame(OrderStatus::Dikonfirmasi, $order->status);
        $this->assertSame(PaymentStatus::Diterima, $payment->status);
        $this->assertSame($this->koordinator->id, $payment->diverifikasi_oleh);
        $this->assertNotNull($payment->diverifikasi_pada);

        $log = $order->statusLogs->sole();
        $this->assertSame(OrderStatus::MenungguVerifikasi, $log->status_lama);
        $this->assertSame(OrderStatus::Dikonfirmasi, $log->status_baru);
        $this->assertSame($this->koordinator->id, $log->created_by);
    }

    public function test_tolak_pembayaran_lalu_pelanggan_bisa_unggah_ulang(): void
    {
        $order = $this->menungguVerifikasi();

        $this->actingAs($this->admin)
            ->postJson($this->url($order, 'verifikasi'), ['keputusan' => 'tolak', 'catatan' => 'Nominal transfer kurang Rp 1.000'])
            ->assertOk()
            ->assertJsonPath('order.status', 'menunggu_pembayaran');

        $order->refresh();
        $payment = $order->payments()->sole();
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertSame(PaymentStatus::Ditolak, $payment->status);
        $this->assertSame('Nominal transfer kurang Rp 1.000', $payment->catatan);
        $this->assertStringContainsString('Nominal transfer kurang', $order->statusLogs()->sole()->keterangan);

        // Pelanggan (session pemilik pesanan) mengunggah ulang bukti.
        $this->withSession([OrderAccess::SESSION_KEY => [$order->kode_pesanan]])
            ->post("/checkout/{$order->kode_pesanan}/pembayaran", [
                'bukti' => UploadedFile::fake()->image('bukti-baru.jpg', 400, 600)->size(300),
            ])
            ->assertRedirect("/checkout/{$order->kode_pesanan}/selesai");

        $this->assertSame(OrderStatus::MenungguVerifikasi, $order->fresh()->status);
        $this->assertSame(
            [PaymentStatus::Ditolak, PaymentStatus::MenungguVerifikasi],
            $order->payments()->orderBy('id')->get()->pluck('status')->all(),
        );
    }

    public function test_verifikasi_ditolak_jika_pesanan_tidak_menunggu_verifikasi(): void
    {
        $order = $this->pesanan(OrderStatus::Dikonfirmasi, PaymentMethod::Transfer, PaymentStatus::Diterima);

        $this->actingAs($this->admin)
            ->postJson($this->url($order, 'verifikasi'), ['keputusan' => 'terima'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($this->admin)
            ->postJson($this->url($this->menungguVerifikasi(), 'verifikasi'), ['keputusan' => 'mungkin'])
            ->assertJsonValidationErrors(['keputusan' => 'Keputusan verifikasi tidak valid.']);
    }

    public function test_detail_pesanan_untuk_modal(): void
    {
        $order = $this->menungguVerifikasi();
        $paymentId = $order->payments()->value('id');

        $this->actingAs($this->admin)->getJson($this->url($order))
            ->assertOk()
            ->assertJsonPath('kode', $order->kode_pesanan)
            ->assertJsonPath('total_formatted', 'Rp 46.000')
            ->assertJsonPath('items.0.nama', 'Original')
            ->assertJsonPath('payment.status', 'menunggu_verifikasi')
            ->assertJsonPath('payment.bukti_url', route('internal.payments.proof', $paymentId))
            ->assertJsonPath('bisa_verifikasi', true);
    }

    // ── Bukti pembayaran privat ──────────────────────────────

    public function test_bukti_pembayaran_disimpan_privat_dan_hanya_untuk_admin_koordinator(): void
    {
        $order = $this->menungguVerifikasi();
        $payment = $order->payments()->sole();
        $url = "/internal/pembayaran/{$payment->id}/bukti";

        Storage::disk('public')->assertMissing($payment->bukti_transfer);

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $this->actingAs($this->koordinator)->get($url)->assertOk()->headers->get('Cache-Control'));

        $this->actingAs($this->staf)->get($url)->assertForbidden();
    }

    public function test_staf_tidak_bisa_verifikasi_maupun_melihat_data_pembayaran(): void
    {
        $order = $this->menungguVerifikasi();

        $this->actingAs($this->staf)
            ->postJson($this->url($order, 'verifikasi'), ['keputusan' => 'terima'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya Admin atau Koordinator yang dapat memverifikasi pembayaran.');
        $this->actingAs($this->staf)->getJson($this->url($order))->assertForbidden();

        $this->assertSame(OrderStatus::MenungguVerifikasi, $order->fresh()->status);
        $this->assertSame(PaymentStatus::MenungguVerifikasi, $order->payments()->sole()->status);
    }

    // ── Alur status produksi ─────────────────────────────────

    public function test_status_produksi_berurutan_sampai_selesai(): void
    {
        $order = $this->pesanan(OrderStatus::Dikonfirmasi, PaymentMethod::Qris, PaymentStatus::Diterima);

        foreach (['diproses' => 'Tandai Siap', 'siap' => 'Selesaikan', 'selesai' => null] as $status => $tombolBerikutnya) {
            $res = $this->actingAs($this->staf)
                ->postJson($this->url($order, 'status'), ['status' => $status])
                ->assertOk()
                ->assertJsonPath('order.status', $status);

            if ($tombolBerikutnya) {
                $this->assertStringContainsString($tombolBerikutnya, $res->json('row'));
            }
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Selesai, $order->status);
        $this->assertSame(
            [['dikonfirmasi', 'diproses'], ['diproses', 'siap'], ['siap', 'selesai']],
            $order->statusLogs->map(fn ($l) => [$l->status_lama->value, $l->status_baru->value])->all(),
        );
        $this->assertSame([$this->staf->id], $order->statusLogs->pluck('created_by')->unique()->values()->all());
    }

    public function test_status_produksi_tidak_boleh_loncat_atau_mundur(): void
    {
        $dikonfirmasi = $this->pesanan(OrderStatus::Dikonfirmasi);
        $siap = $this->pesanan(OrderStatus::Siap);
        $belumBayar = $this->pesanan(OrderStatus::MenungguPembayaran);

        $kirim = fn (Order $o, string $status) => $this->actingAs($this->admin)->postJson($this->url($o, 'status'), ['status' => $status]);

        $kirim($dikonfirmasi, 'siap')->assertUnprocessable()->assertJsonValidationErrors('status');     // loncat
        $kirim($dikonfirmasi, 'selesai')->assertUnprocessable();                                         // loncat
        $kirim($siap, 'diproses')->assertUnprocessable();                                                // mundur
        $kirim($belumBayar, 'diproses')->assertUnprocessable();                                          // belum dibayar
        $kirim($dikonfirmasi, 'dibatalkan')
            ->assertJsonValidationErrors(['status' => 'Status tujuan harus salah satu langkah produksi: Diproses, Siap, atau Selesai.']);

        $this->assertSame(OrderStatus::Dikonfirmasi, $dikonfirmasi->fresh()->status);
        $this->assertSame(OrderStatus::Siap, $siap->fresh()->status);
        $this->assertSame(OrderStatus::MenungguPembayaran, $belumBayar->fresh()->status);
        $this->assertDatabaseCount('order_status_logs', 0);
    }

    public function test_staf_tidak_bisa_mengubah_pesanan_di_luar_antrean_produksi(): void
    {
        $verifikasi = $this->menungguVerifikasi();
        $selesai = $this->pesanan(OrderStatus::Selesai);

        $this->actingAs($this->staf)->postJson($this->url($verifikasi, 'status'), ['status' => 'diproses'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Pesanan ini belum masuk antrean produksi.');
        $this->actingAs($this->staf)->postJson($this->url($selesai, 'status'), ['status' => 'selesai'])->assertForbidden();
    }

    // ── Tunai ────────────────────────────────────────────────

    public function test_tandai_lunas_tunai_saat_pesanan_siap(): void
    {
        $order = $this->pesanan(OrderStatus::Siap, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);

        $this->actingAs($this->admin)->get('/internal/pesanan')->assertSee('Tandai Lunas (Tunai)');

        $res = $this->actingAs($this->koordinator)->postJson($this->url($order, 'lunas'))
            ->assertOk()
            ->assertJsonPath('message', "Pembayaran tunai {$order->kode_pesanan} ditandai lunas.");
        $this->assertStringNotContainsString('Tandai Lunas', $res->json('row'));
        $this->assertStringContainsString('Diterima', $res->json('row'));

        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Diterima, $payment->status);
        $this->assertSame($this->koordinator->id, $payment->diverifikasi_oleh);
        $this->assertSame(OrderStatus::Siap, $order->fresh()->status); // status pesanan tidak berubah

        $this->actingAs($this->koordinator)->postJson($this->url($order, 'lunas'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => "Pembayaran tunai {$order->kode_pesanan} sudah ditandai lunas."]);
    }

    public function test_tandai_lunas_hanya_untuk_tunai_yang_siap_atau_selesai(): void
    {
        $tunaiBelumSiap = $this->pesanan(OrderStatus::Dikonfirmasi, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);
        $transferSiap = $this->pesanan(OrderStatus::Siap, PaymentMethod::Transfer, PaymentStatus::Diterima);
        $tunaiSelesai = $this->pesanan(OrderStatus::Selesai, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);

        $this->actingAs($this->admin)->get('/internal/pesanan')
            ->assertSee('Tandai Lunas (Tunai)'); // untuk $tunaiSelesai

        $this->actingAs($this->admin)->postJson($this->url($tunaiBelumSiap, 'lunas'))->assertUnprocessable();
        $this->actingAs($this->admin)->postJson($this->url($transferSiap, 'lunas'))
            ->assertJsonValidationErrors(['status' => "Pesanan {$transferSiap->kode_pesanan} bukan pesanan tunai."]);
        $this->actingAs($this->admin)->postJson($this->url($tunaiSelesai, 'lunas'))->assertOk();
    }

    public function test_staf_kasir_bisa_menandai_lunas_tunai_saat_serah_terima(): void
    {
        $siap = $this->pesanan(OrderStatus::Siap, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);
        $selesai = $this->pesanan(OrderStatus::Selesai, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);

        $this->actingAs($this->staf)->get('/internal/pesanan')
            ->assertSee($siap->kode_pesanan)
            ->assertSee('Tandai Lunas (Tunai)')
            ->assertSee('sebesar Rp 46.000'); // kasir perlu tahu nominal yang ditagih

        $res = $this->actingAs($this->staf)->postJson($this->url($siap, 'lunas'))->assertOk();
        $this->assertStringNotContainsString('Tandai Lunas', $res->json('row'));
        // Kasir tetap tidak melihat kolom pembayaran di baris yang dikembalikan.
        $this->assertStringNotContainsString('Rp 46.000', $res->json('row'));

        $payment = $siap->payments()->sole();
        $this->assertSame(PaymentStatus::Diterima, $payment->status);
        $this->assertSame($this->staf->id, $payment->diverifikasi_oleh);

        // Pesanan yang terlanjur diselesaikan sebelum ditandai lunas tetap bisa dilunasi kasir.
        $this->actingAs($this->staf)->postJson($this->url($selesai, 'lunas'))->assertOk();
        $this->assertSame(PaymentStatus::Diterima, $selesai->payments()->sole()->status);
    }

    public function test_staf_tetap_tidak_bisa_verifikasi_transfer_atau_melihat_bukti_walau_boleh_menandai_lunas(): void
    {
        $transfer = $this->menungguVerifikasi();
        $paymentId = $transfer->payments()->value('id');
        $tunaiBelumSiap = $this->pesanan(OrderStatus::Diproses, PaymentMethod::Cash, PaymentStatus::MenungguPembayaran);

        $this->actingAs($this->staf)->postJson($this->url($transfer, 'verifikasi'), ['keputusan' => 'terima'])->assertForbidden();
        $this->actingAs($this->staf)->get("/internal/pembayaran/{$paymentId}/bukti")->assertForbidden();
        $this->actingAs($this->staf)->getJson($this->url($transfer))->assertForbidden();

        // Belum siap diserahkan -> belum boleh ditandai lunas.
        $this->actingAs($this->staf)->postJson($this->url($tunaiBelumSiap, 'lunas'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Pembayaran tunai baru bisa ditandai lunas saat pesanan Siap Diambil/Dikirim atau Selesai.');

        // Pesanan non-tunai yang Siap ditolak oleh aturan bisnis (bukan tunai).
        $transferSiap = $this->pesanan(OrderStatus::Siap, PaymentMethod::Transfer, PaymentStatus::Diterima);
        $this->actingAs($this->staf)->postJson($this->url($transferSiap, 'lunas'))->assertUnprocessable();

        $this->assertSame(OrderStatus::MenungguVerifikasi, $transfer->fresh()->status);
        $this->assertSame(PaymentStatus::MenungguPembayaran, $tunaiBelumSiap->payments()->sole()->status);
    }
}
