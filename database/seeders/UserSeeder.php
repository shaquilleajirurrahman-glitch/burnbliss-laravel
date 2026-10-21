<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Akun demo untuk tiap level user internal. Password default: "password" (khusus lokal).
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Admin Burn & Bliss', 'email' => 'admin@burnbliss.com', 'role' => UserRole::Admin],
            ['name' => 'Koordinator Produksi', 'email' => 'koordinator@burnbliss.com', 'role' => UserRole::Koordinator],
            ['name' => 'Staf Kasir', 'email' => 'staf@burnbliss.com', 'role' => UserRole::Staf],
        ];

        foreach ($accounts as $account) {
            // forceCreate karena "role" tidak termasuk fillable.
            User::forceCreate($account + [
                'password' => 'password', // di-hash otomatis oleh cast "hashed"
                'email_verified_at' => now(),
            ]);
        }
    }
}
