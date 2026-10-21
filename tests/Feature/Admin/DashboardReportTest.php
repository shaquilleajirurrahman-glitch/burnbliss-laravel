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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $staf;

    private ProductVariant $original; // Rp 23.000

    private ProductVariant $oreo;     // Rp 25.000

    /** @var array<string, Order> */
    private array $o = [];

    private int $urutan = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 20 Oktober 2026 10:00 WIB.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00'));

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->koordinator = User::factory()->role(UserRole::Koordinator)->create();
        $this->staf = User::factory()->role(UserRole::Staf)->create();

        $product = Product::create(['nama' => 'Basque Burnt Cheesecake', 'harga' => 23000]);
        $this->original = $product->variants()->create(['nama_varian' => 'Original', 'stok' => 99, 'harga_tambahan' => 0]);
        $this->oreo = $product->variants()->create(['nama_varian' => 'Oreo', 'stok' => 99, 'harga_tambahan' => 2000]);

        // Lunas (dihitung):
        $this->o['A'] = $this->pesanan('2026-10-20 08:00', OrderStatus::Dikonfirmasi, PaymentStatus::Diterima, [$this->original->id => 2], hp: '081111111111'); // 46.000
        $this->o['C'] = $this->pesanan('2026-10-05 12:00', OrderStatus::Selesai, PaymentStatus::Diterima, [$this->original->id => 1, $this->oreo->id => 2], hp: '081111111111'); // 73.000 (pelanggan sama dgn A)
        $this->o['D'] = $this->pesanan('2026-10-10 15:00', OrderStatus::Diproses, PaymentStatus::Diterima, [$this->oreo->id => 1]); // 25.000 (sedang diproduksi tetap dihitung)
        $this->o['E'] = $this->pesanan('2026-09-28 09:00', OrderStatus::Selesai, PaymentStatus::Diterima, [$this->original->id => 3]); // 69.000 (bulan lalu)

        // Belum lunas (tidak dihitung):
        $this->o['B'] = $this->pesanan('2026-10-20 09:00', OrderStatus::MenungguVerifikasi, PaymentStatus::MenungguVerifikasi, [$this->oreo->id => 1]);
        $this->o['F'] = $this->pesanan('2026-10-12 11:00', OrderStatus::MenungguPembayaran, null, [$this->original->id => 1]);
        $this->o['G'] = $this->pesanan('2026-10-15 16:00', OrderStatus::Siap, PaymentStatus::MenungguPembayaran, [$this->oreo->id => 2], PaymentMethod::Cash);
    }

    /**
     * @param  array<int, int>  $items  variant_id => jumlah
     */
    private function pesanan(
        string $dibuat,
        OrderStatus $status,
        ?PaymentStatus $bayar,
        array $items,
        PaymentMethod $metode = PaymentMethod::Transfer,
        ?string $nama = null,
        ?string $hp = null,
    ): Order {
        $this->urutan++;
        $harga = [$this->original->id => 23000, $this->oreo->id => 25000];
        $total = collect($items)->sum(fn ($qty, $id) => $harga[$id] * $qty);

        $order = Order::create([
            'kode_pesanan' => sprintf('BNB-2026-R%05d', $this->urutan),
            'nama_pelanggan' => $nama ?? "Pelanggan {$this->urutan}",
            'nomor_hp' => $hp ?? sprintf('0813%08d', $this->urutan),
            'total_harga' => $total,
            'status' => $status,
            'metode_pengiriman' => ShippingMethod::AmbilSendiri,
            'metode_pembayaran' => $metode,
            'tanggal_ambil' => '2026-10-25',
        ]);
        $order->forceFill(['created_at' => CarbonImmutable::parse($dibuat)])->save();

        foreach ($items as $id => $qty) {
            $order->items()->create(['product_variant_id' => $id, 'jumlah' => $qty, 'harga_satuan' => $harga[$id], 'subtotal' => $harga[$id] * $qty]);
        }

        if ($bayar) {
            $order->payments()->create(['metode' => $metode, 'jumlah' => $total, 'status' => $bayar]);
        }

        return $order;
    }

    /**
     * Baris data CSV (tanpa BOM & tanpa header).
     *
     * @return list<list<string>>
     */
    private function csv(string $isi): array
    {
        $baris = array_map(fn ($b) => str_getcsv($b, ',', '"', ''), explode("\n", trim(ltrim($isi, "\xEF\xBB\xBF"))));
        array_shift($baris);

        return $baris;
    }

    // ── Akses ────────────────────────────────────────────────

    public function test_dashboard_dan_laporan_hanya_untuk_admin_dan_koordinator(): void
    {
        $urls = ['/internal/dashboard', '/internal/laporan', '/internal/laporan/export'];

        // Tamu dicek lebih dulu: actingAs() berlaku untuk request-request berikutnya dalam test.
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        foreach ($urls as $url) {
            $this->actingAs($this->staf)->get($url)->assertForbidden();
            $this->actingAs($this->koordinator)->get($url)->assertOk();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_navbar_menampilkan_dashboard_dan_laporan_sesuai_role(): void
    {
        $this->actingAs($this->admin)->get('/internal/pesanan')->assertSee('/internal/dashboard')->assertSee('/internal/laporan');
        $this->actingAs($this->staf)->get('/internal/pesanan')->assertDontSee('/internal/dashboard')->assertDontSee('/internal/laporan');
    }

    // ── Dashboard ────────────────────────────────────────────

    public function test_dashboard_menampilkan_angka_yang_benar(): void
    {
        $this->actingAs($this->admin)->get('/internal/dashboard')
            ->assertOk()
            ->assertViewHas('pesananHariIni', 2)       // A & B dibuat hari ini
            ->assertViewHas('menungguVerifikasi', 1)   // B
            ->assertViewHas('omzetBulanIni', 144000)   // A 46.000 + C 73.000 + D 25.000
            ->assertViewHas('pelangganUnik', 6)        // A & C nomor HP sama
            ->assertViewHas('antreanProduksi', 3)      // A dikonfirmasi, D diproses, G siap
            ->assertSee('Rp 144.000')
            ->assertSee('Omzet Oktober 2026');
    }

    public function test_dashboard_menampilkan_5_pesanan_terbaru(): void
    {
        $page = $this->actingAs($this->admin)->get('/internal/dashboard')->assertOk();

        // Urut tanggal dibuat terbaru: B (20/10 09:00), A (20/10 08:00), G (15/10), F (12/10), D (10/10).
        $page->assertSeeInOrder(array_map(fn ($k) => $this->o[$k]->kode_pesanan, ['B', 'A', 'G', 'F', 'D']));
        $page->assertDontSee($this->o['C']->kode_pesanan)->assertDontSee($this->o['E']->kode_pesanan);
    }

    // ── Laporan ──────────────────────────────────────────────

    public function test_laporan_default_bulan_ini_hanya_pesanan_lunas(): void
    {
        $page = $this->actingAs($this->admin)->get('/internal/laporan')->assertOk();

        $page->assertViewHas('ringkasan', ['jumlah_pesanan' => 3, 'total_pcs' => 6, 'total_pendapatan' => 144000])
            ->assertSee('Rp 144.000')
            ->assertSeeInOrder([$this->o['C']->kode_pesanan, $this->o['D']->kode_pesanan, $this->o['A']->kode_pesanan])
            ->assertDontSee($this->o['B']->kode_pesanan)  // menunggu verifikasi
            ->assertDontSee($this->o['F']->kode_pesanan)  // belum bayar
            ->assertDontSee($this->o['G']->kode_pesanan)  // tunai belum lunas
            ->assertDontSee($this->o['E']->kode_pesanan); // bulan lalu
    }

    public function test_omzet_dashboard_sama_dengan_grand_total_laporan_bulan_ini(): void
    {
        $omzet = $this->actingAs($this->admin)->get('/internal/dashboard')->viewData('omzetBulanIni');
        $laporan = $this->actingAs($this->admin)->getJson('/internal/laporan?dari=2026-10-01&sampai=2026-10-31');

        $this->assertSame($omzet, $laporan->json('ringkasan.total_pendapatan'));
    }

    public function test_laporan_memfilter_tanggal_dan_varian_via_ajax(): void
    {
        $this->actingAs($this->admin)->getJson('/internal/laporan?dari=2026-09-01&sampai=2026-09-30')
            ->assertOk()
            ->assertJsonStructure(['html', 'export_url', 'ringkasan' => ['jumlah_pesanan', 'total_pcs', 'total_pendapatan', 'total_pendapatan_formatted']])
            ->assertJsonPath('ringkasan.total_pendapatan', 69000)
            ->assertJsonPath('ringkasan.total_pendapatan_formatted', 'Rp 69.000');

        $rentang = $this->actingAs($this->admin)->getJson('/internal/laporan?dari=2026-10-06&sampai=2026-10-31');
        $this->assertSame(71000, $rentang->json('ringkasan.total_pendapatan')); // D + A
        $this->assertStringNotContainsString($this->o['C']->kode_pesanan, $rentang->json('html'));

        $oreo = $this->actingAs($this->admin)->getJson("/internal/laporan?dari=2026-10-01&sampai=2026-10-31&varian={$this->oreo->id}")
            ->assertJsonPath('ringkasan.total_pcs', 3)              // C 2 pcs + D 1 pcs
            ->assertJsonPath('ringkasan.total_pendapatan', 75000);
        $this->assertStringNotContainsString($this->o['A']->kode_pesanan, $oreo->json('html')); // A hanya Original
        $this->assertStringContainsString(
            "dari=2026-10-01&amp;sampai=2026-10-31&amp;varian={$this->oreo->id}",
            e($oreo->json('export_url')),
        );
    }

    public function test_laporan_memvalidasi_filter(): void
    {
        $this->actingAs($this->admin)->getJson('/internal/laporan?dari=2026-10-20&sampai=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sampai' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        $this->actingAs($this->admin)->getJson('/internal/laporan?varian=9999')->assertJsonValidationErrors('varian');

        // Non-AJAX: kembali ke laporan default (bukan redirect berulang ke URL yang sama).
        $this->actingAs($this->admin)->get('/internal/laporan?dari=salah')
            ->assertRedirect(route('internal.reports.index'));
    }

    // ── Ekspor CSV ───────────────────────────────────────────

    public function test_ekspor_csv_mengikuti_filter_dan_bisa_diunduh(): void
    {
        $res = $this->actingAs($this->admin)->get('/internal/laporan/export?dari=2026-10-01&sampai=2026-10-20');

        $res->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('laporan-penjualan-2026-10-20.csv');

        $isi = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi); // BOM untuk Excel
        $this->assertStringContainsString('Tanggal,"Kode Pesanan","Nama Pelanggan",Varian,"Jumlah Terjual","Harga Satuan","Total Pendapatan"', $isi);

        $baris = $this->csv($isi);
        $this->assertCount(5, $baris); // 4 item lunas + baris GRAND TOTAL
        $this->assertSame(['2026-10-05', $this->o['C']->kode_pesanan, 'Pelanggan 2', 'Original', '1', '23000', '23000'], $baris[0]);
        $this->assertSame(['GRAND TOTAL', '', '', '', '6', '', '144000'], end($baris));

        // Filter varian ikut berlaku di CSV.
        $oreo = $this->csv($this->actingAs($this->admin)
            ->get("/internal/laporan/export?dari=2026-10-01&sampai=2026-10-20&varian={$this->oreo->id}")->streamedContent());
        $this->assertSame(['Oreo', 'Oreo'], array_column(array_slice($oreo, 0, -1), 3));
        $this->assertSame('75000', end($oreo)[6]);
    }

    public function test_ekspor_csv_mencegah_formula_injection_dari_nama_pelanggan(): void
    {
        $this->pesanan('2026-10-18 10:00', OrderStatus::Selesai, PaymentStatus::Diterima, [$this->original->id => 1], nama: '=HYPERLINK("http://jahat.test","Klik")');

        $isi = $this->actingAs($this->admin)->get('/internal/laporan/export?dari=2026-10-18&sampai=2026-10-18')->streamedContent();

        $this->assertSame('\'=HYPERLINK("http://jahat.test","Klik")', $this->csv($isi)[0][2]);
    }
}
