<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthRoleTest extends TestCase
{
    use RefreshDatabase;

    private const DASHBOARDS = ['/admin/dashboard', '/koordinator/dashboard', '/staf/dashboard'];

    public function test_tamu_diarahkan_ke_login_saat_membuka_dashboard(): void
    {
        foreach (self::DASHBOARDS as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_halaman_login_internal_dapat_dibuka_tanpa_tautan_registrasi(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertDontSee('Daftar sekarang');
    }

    public function test_registrasi_publik_dan_dashboard_pelanggan_tidak_ada(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com'])->assertNotFound();
        $this->get('/pelanggan/dashboard')->assertNotFound();
    }

    /**
     * @return array<string, array{UserRole, string}>
     */
    public static function loginPerRole(): array
    {
        return [
            'admin' => [UserRole::Admin, '/admin/dashboard'],
            'koordinator' => [UserRole::Koordinator, '/koordinator/dashboard'],
            'staf' => [UserRole::Staf, '/staf/dashboard'],
        ];
    }

    #[DataProvider('loginPerRole')]
    public function test_login_mengarahkan_ke_dashboard_sesuai_role(UserRole $role, string $dashboard): void
    {
        $user = User::factory()->role($role)->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($dashboard);

        $this->assertAuthenticatedAs($user);
        $this->get($dashboard)
            ->assertOk()
            ->assertSee("Selamat datang, {$user->name} - Role: {$role->label()}");
    }

    /**
     * Matriks hak akses hierarkis (Admin > Koordinator > Staf): [role, url, status yang diharapkan].
     *
     * @return array<string, array{UserRole, string, int}>
     */
    public static function matriksAkses(): array
    {
        return [
            'admin → admin' => [UserRole::Admin, '/admin/dashboard', 200],
            'admin → koordinator' => [UserRole::Admin, '/koordinator/dashboard', 200],
            'admin → staf' => [UserRole::Admin, '/staf/dashboard', 200],
            'koordinator → admin' => [UserRole::Koordinator, '/admin/dashboard', 403],
            'koordinator → koordinator' => [UserRole::Koordinator, '/koordinator/dashboard', 200],
            'koordinator → staf' => [UserRole::Koordinator, '/staf/dashboard', 200],
            'staf → admin' => [UserRole::Staf, '/admin/dashboard', 403],
            'staf → koordinator' => [UserRole::Staf, '/koordinator/dashboard', 403],
            'staf → staf' => [UserRole::Staf, '/staf/dashboard', 200],
        ];
    }

    #[DataProvider('matriksAkses')]
    public function test_matriks_hak_akses_dashboard(UserRole $role, string $url, int $status): void
    {
        $this->actingAs(User::factory()->role($role)->create())
            ->get($url)
            ->assertStatus($status);
    }

    public function test_atasan_yang_membuka_panel_bawahan_tetap_melihat_role_sendiri(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get('/staf/dashboard')
            ->assertOk()
            ->assertSee('Staf Panel')
            ->assertSee("Selamat datang, {$admin->name} - Role: Administrator");
    }

    public function test_hierarki_level_role(): void
    {
        $this->assertTrue(UserRole::Admin->isAtLeast(UserRole::Koordinator));
        $this->assertTrue(UserRole::Admin->isAtLeast(UserRole::Staf));
        $this->assertTrue(UserRole::Koordinator->isAtLeast(UserRole::Staf));
        $this->assertTrue(UserRole::Staf->isAtLeast(UserRole::Staf));
        $this->assertFalse(UserRole::Koordinator->isAtLeast(UserRole::Admin));
        $this->assertFalse(UserRole::Staf->isAtLeast(UserRole::Koordinator));
    }

    /**
     * @return array<string, array{UserRole, list<string>, list<string>}>
     */
    public static function menuPerRole(): array
    {
        return [
            'admin' => [UserRole::Admin, ['Admin Panel', 'Koordinator Panel', 'Staf Panel'], []],
            'koordinator' => [UserRole::Koordinator, ['Koordinator Panel', 'Staf Panel'], ['Admin Panel']],
            'staf' => [UserRole::Staf, ['Staf Panel'], ['Admin Panel', 'Koordinator Panel']],
        ];
    }

    /**
     * @param  list<string>  $tampil
     * @param  list<string>  $tersembunyi
     */
    #[DataProvider('menuPerRole')]
    public function test_navbar_menampilkan_panel_sesuai_hierarki(UserRole $role, array $tampil, array $tersembunyi): void
    {
        $nav = $this->navbarHtml(User::factory()->role($role)->create(), $role);

        foreach ($tampil as $label) {
            $this->assertStringContainsString($label, $nav);
        }
        foreach ($tersembunyi as $label) {
            $this->assertStringNotContainsString($label, $nav);
        }
    }

    /**
     * Pengaman: menu di navbar (UserRole::level) harus selalu sinkron dengan middleware route.
     * Menu tampil => route 200; menu tersembunyi => route 403.
     */
    public function test_menu_navbar_sinkron_dengan_hak_akses_route(): void
    {
        $panels = [
            'Admin Panel' => '/admin/dashboard',
            'Koordinator Panel' => '/koordinator/dashboard',
            'Staf Panel' => '/staf/dashboard',
        ];

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $nav = $this->navbarHtml($user, $role);

            foreach ($panels as $label => $url) {
                $tampil = str_contains($nav, $label);
                $status = $this->actingAs($user)->get($url)->getStatusCode();

                $this->assertSame(
                    $tampil ? 200 : 403,
                    $status,
                    "{$role->value}: menu '{$label}' ".($tampil ? 'tampil' : 'tersembunyi')." tetapi {$url} = {$status}",
                );
            }
        }
    }

    /**
     * Ambil HTML elemen <nav> saja dari dashboard milik user, agar teks di isi halaman
     * (mis. label panel) tidak ikut terhitung sebagai menu.
     */
    private function navbarHtml(User $user, UserRole $role): string
    {
        $html = $this->actingAs($user)->get(route($role->dashboardRoute()))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<nav\b.*?</nav>#s', $html, $match), 'Elemen <nav> tidak ditemukan.');

        return $match[0];
    }

    public function test_password_salah_ditolak_dengan_pesan(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah-total'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_login_memvalidasi_input(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'bukan-email', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_dibatasi_5_kali_per_menit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_user_login_yang_membuka_halaman_login_diarahkan_ke_dashboardnya(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Koordinator)->create())
            ->get('/login')
            ->assertRedirect('/koordinator/dashboard');
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Staf)->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/staf/dashboard')->assertRedirect('/login');
    }
}
