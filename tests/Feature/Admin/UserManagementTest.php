<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $koordinator;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin Utama', 'email' => 'admin@burnbliss.com']);
        $this->koordinator = User::factory()->role(UserRole::Koordinator)->create(['name' => 'Koordinator Produksi', 'email' => 'koordinator@burnbliss.com']);
        $this->staf = User::factory()->role(UserRole::Staf)->create(['name' => 'Staf Kasir', 'email' => 'staf@burnbliss.com']);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function dataBaru(array $override = []): array
    {
        return array_merge([
            'name' => 'Rina Kasir',
            'email' => 'Rina@BurnBliss.com',
            'role' => 'staf',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ], $override);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function dataUbah(User $user, array $override = []): array
    {
        return array_merge(['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value], $override);
    }

    // ── Hak akses ────────────────────────────────────────────

    public function test_hanya_admin_yang_bisa_mengelola_pengguna(): void
    {
        $this->get('/internal/pengguna')->assertRedirect('/login');

        foreach ([$this->koordinator, $this->staf] as $user) {
            $this->actingAs($user)->get('/internal/pengguna')->assertForbidden();
            $this->actingAs($user)->get('/internal/pengguna/create')->assertForbidden();
            $this->actingAs($user)->post('/internal/pengguna', $this->dataBaru())->assertForbidden();
            $this->actingAs($user)->get("/internal/pengguna/{$this->staf->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/internal/pengguna/{$this->staf->id}", $this->dataUbah($this->staf, ['role' => 'admin']))->assertForbidden();
            $this->actingAs($user)->delete("/internal/pengguna/{$this->admin->id}")->assertForbidden();
        }

        $this->assertSame(UserRole::Staf, $this->staf->fresh()->role); // tidak bisa menaikkan role sendiri
        $this->assertDatabaseCount('users', 3);

        $this->actingAs($this->admin)->get('/internal/pengguna')->assertOk();
        $this->actingAs($this->admin)->get('/internal/pengguna/create')->assertOk();
    }

    public function test_menu_pengguna_hanya_untuk_admin(): void
    {
        $this->actingAs($this->admin)->get('/internal/pesanan')->assertSee('/internal/pengguna');
        $this->actingAs($this->koordinator)->get('/internal/pesanan')->assertDontSee('/internal/pengguna');
        $this->actingAs($this->staf)->get('/internal/pesanan')->assertDontSee('/internal/pengguna');
    }

    // ── Read ─────────────────────────────────────────────────

    public function test_daftar_pengguna_menampilkan_tiga_level_tanpa_password(): void
    {
        $page = $this->actingAs($this->admin)->get('/internal/pengguna')->assertOk();

        $page->assertSeeInOrder(['Admin Utama', 'Koordinator Produksi', 'Staf Kasir']) // urut hierarki
            ->assertSee(['Level 3 · Administrator', 'Level 2 · Koordinator Produksi', 'Level 1 · Staf/Kasir'])
            ->assertSee('koordinator@burnbliss.com')
            ->assertDontSee($this->admin->password)
            ->assertDontSee('$2y$', false);

        // Filter per role.
        $this->actingAs($this->admin)->get('/internal/pengguna?role=staf')
            ->assertSee('Staf Kasir')
            ->assertDontSee('koordinator@burnbliss.com');
    }

    // ── Create ───────────────────────────────────────────────

    public function test_admin_membuat_pengguna_dengan_password_ter_hash(): void
    {
        $this->actingAs($this->admin)->post('/internal/pengguna', $this->dataBaru())
            ->assertRedirect('/internal/pengguna')
            ->assertSessionHas('status', 'Akun Rina Kasir (Staf/Kasir) dibuat.');

        $user = User::where('email', 'rina@burnbliss.com')->sole(); // email disimpan huruf kecil
        $this->assertSame(UserRole::Staf, $user->role);
        $this->assertNotSame('rahasia-123', $user->password);
        $this->assertTrue(Hash::check('rahasia-123', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);

        // Akun baru langsung bisa login dan diarahkan ke dashboard sesuai role.
        auth()->logout();
        $this->post('/login', ['email' => 'rina@burnbliss.com', 'password' => 'rahasia-123'])
            ->assertRedirect('/staf/dashboard');
    }

    public function test_tambah_pengguna_memvalidasi_input(): void
    {
        $this->actingAs($this->admin)->post('/internal/pengguna', $this->dataBaru([
            'name' => '',
            'email' => 'KOORDINATOR@burnbliss.com', // sudah dipakai (beda huruf besar/kecil)
            'role' => 'superadmin',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ]))->assertSessionHasErrors([
            'name' => 'Nama wajib diisi.',
            'email' => 'Email ini sudah dipakai akun lain (termasuk akun yang sudah dihapus).',
            'role' => 'Role tidak valid. Pilih Admin, Koordinator, atau Staf.',
            'password' => 'Konfirmasi password tidak cocok.',
        ]);

        $this->actingAs($this->admin)->post('/internal/pengguna', $this->dataBaru(['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);

        $this->assertDatabaseCount('users', 3);
    }

    // ── Update ───────────────────────────────────────────────

    public function test_admin_mengubah_pengguna_tanpa_mengganti_password(): void
    {
        $hashLama = $this->staf->password;

        $this->actingAs($this->admin)->get("/internal/pengguna/{$this->staf->id}/edit")
            ->assertOk()
            ->assertSee('staf@burnbliss.com')
            ->assertDontSee($hashLama);

        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->staf->id}", $this->dataUbah($this->staf, [
            'name' => 'Staf Kasir Senior',
            'role' => 'koordinator',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertRedirect('/internal/pengguna')->assertSessionHas('status', 'Akun Staf Kasir Senior diperbarui.');

        $staf = $this->staf->fresh();
        $this->assertSame('Staf Kasir Senior', $staf->name);
        $this->assertSame(UserRole::Koordinator, $staf->role);
        $this->assertSame($hashLama, $staf->password); // password tidak berubah
    }

    public function test_admin_mengganti_password_pengguna(): void
    {
        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->staf->id}", $this->dataUbah($this->staf, [
            'password' => 'password-baru-9',
            'password_confirmation' => 'password-baru-9',
        ]))->assertSessionHas('status', 'Akun Staf Kasir diperbarui (password diganti).');

        $this->assertTrue(Hash::check('password-baru-9', $this->staf->fresh()->password));
        $this->assertFalse(Hash::check('password', $this->staf->fresh()->password));
    }

    public function test_email_unik_kecuali_milik_sendiri(): void
    {
        // Email tetap sama milik sendiri: boleh.
        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->staf->id}", $this->dataUbah($this->staf, ['name' => 'Ganti Nama']))
            ->assertSessionHasNoErrors();

        // Email milik pengguna lain: ditolak.
        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->staf->id}", $this->dataUbah($this->staf, ['email' => 'koordinator@burnbliss.com']))
            ->assertSessionHasErrors(['email' => 'Email ini sudah dipakai akun lain (termasuk akun yang sudah dihapus).']);

        $this->assertSame('staf@burnbliss.com', $this->staf->fresh()->email);
    }

    public function test_admin_tidak_bisa_mengubah_role_akunnya_sendiri(): void
    {
        $this->actingAs($this->admin)->get("/internal/pengguna/{$this->admin->id}/edit")
            ->assertSee('Role akun Anda sendiri tidak bisa diubah.');

        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->admin->id}", $this->dataUbah($this->admin, ['role' => 'staf']))
            ->assertSessionHasErrors(['role' => 'Anda tidak bisa mengubah role akun Anda sendiri.']);
        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);

        // Data lain milik sendiri tetap boleh diubah.
        $this->actingAs($this->admin)->put("/internal/pengguna/{$this->admin->id}", $this->dataUbah($this->admin, ['name' => 'Admin Baru']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Admin Baru', $this->admin->fresh()->name);
    }

    // ── Delete (soft delete) ─────────────────────────────────

    public function test_hapus_pengguna_soft_delete_tidak_bisa_login_tapi_riwayat_tetap(): void
    {
        // Staf pernah menandai lunas & mengubah status sebuah pesanan.
        $order = Order::create([
            'kode_pesanan' => 'BNB-2026-AUDIT1', 'nama_pelanggan' => 'Pelanggan', 'nomor_hp' => '081200000001',
            'total_harga' => 23000, 'status' => OrderStatus::Selesai, 'metode_pengiriman' => ShippingMethod::AmbilSendiri,
            'metode_pembayaran' => PaymentMethod::Cash, 'tanggal_ambil' => now()->toDateString(),
        ]);
        $payment = $order->payments()->create(['metode' => PaymentMethod::Cash, 'jumlah' => 23000, 'status' => PaymentStatus::Diterima, 'diverifikasi_oleh' => $this->staf->id]);
        $log = $order->statusLogs()->create(['status_lama' => OrderStatus::Siap, 'status_baru' => OrderStatus::Selesai, 'created_by' => $this->staf->id]);

        $this->actingAs($this->admin)->delete("/internal/pengguna/{$this->staf->id}")
            ->assertRedirect('/internal/pengguna')
            ->assertSessionHas('status', 'Akun Staf Kasir dihapus dan tidak bisa login lagi.');

        $this->assertSoftDeleted($this->staf);
        $this->actingAs($this->admin)->get('/internal/pengguna')->assertDontSee('staf@burnbliss.com');

        // Jejak audit tetap menyebut nama petugas.
        $this->assertSame('Staf Kasir', $payment->fresh()->verifier->name);
        $this->assertSame('Staf Kasir', $log->fresh()->creator->name);

        // Tidak bisa login lagi, dan emailnya tidak bisa langsung dipakai ulang.
        auth()->logout();
        $this->post('/login', ['email' => 'staf@burnbliss.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();

        $this->actingAs($this->admin)->post('/internal/pengguna', $this->dataBaru(['email' => 'staf@burnbliss.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_pengguna_yang_dihapus_keluar_dari_sesi_aktif(): void
    {
        // Login sungguhan (ID user tersimpan di session), bukan actingAs().
        $this->post('/login', ['email' => 'staf@burnbliss.com', 'password' => 'password'])->assertRedirect('/staf/dashboard');
        $this->app['auth']->forgetGuards();
        $this->get('/internal/pesanan')->assertOk();

        $this->staf->delete();

        // Request berikutnya dengan sesi yang sama tidak lagi menemukan akunnya -> diperlakukan sebagai tamu.
        $this->app['auth']->forgetGuards();
        $this->get('/internal/pesanan')->assertRedirect('/login');
    }

    public function test_admin_tidak_bisa_menghapus_akunnya_sendiri(): void
    {
        $this->actingAs($this->admin)->get('/internal/pengguna')
            ->assertSee('Anda')
            ->assertDontSee('action="'.route('internal.users.destroy', $this->admin).'"', false);

        $this->actingAs($this->admin)->delete("/internal/pengguna/{$this->admin->id}")
            ->assertForbidden();
        $this->actingAs($this->admin)->deleteJson("/internal/pengguna/{$this->admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak bisa menghapus akun Anda sendiri yang sedang dipakai login.');

        $this->assertNotSoftDeleted($this->admin);
    }
}
