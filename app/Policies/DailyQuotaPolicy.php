<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DailyQuota;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kuota produksi harian.
 *  - Admin       : lihat, atur, ubah, hapus.
 *  - Koordinator : lihat, atur, ubah (mengatur kapasitas produksi adalah tugasnya), tidak bisa hapus.
 *  - Staf        : tidak punya akses.
 */
class DailyQuotaPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->koordinatorKeAtas($user);
    }

    public function create(User $user): Response
    {
        return $this->koordinatorKeAtas($user);
    }

    public function update(User $user, DailyQuota $quota): Response
    {
        return $this->koordinatorKeAtas($user);
    }

    public function delete(User $user, DailyQuota $quota): Response
    {
        return $user->role === UserRole::Admin
            ? Response::allow()
            : Response::deny('Hanya Admin yang dapat menghapus kuota.');
    }

    private function koordinatorKeAtas(User $user): Response
    {
        return $user->role->isAtLeast(UserRole::Koordinator)
            ? Response::allow()
            : Response::deny('Hanya Admin atau Koordinator yang dapat mengatur kuota produksi.');
    }
}
