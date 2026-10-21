<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kelola akun internal: khusus Admin. Admin tidak bisa menghapus akunnya sendiri.
 */
class UserPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->admin($user);
    }

    public function create(User $user): Response
    {
        return $this->admin($user);
    }

    public function update(User $user, User $model): Response
    {
        return $this->admin($user);
    }

    public function delete(User $user, User $model): Response
    {
        if ($user->is($model)) {
            return Response::deny('Anda tidak bisa menghapus akun Anda sendiri yang sedang dipakai login.');
        }

        return $this->admin($user);
    }

    private function admin(User $user): Response
    {
        return $user->role === UserRole::Admin
            ? Response::allow()
            : Response::deny('Hanya Admin yang dapat mengelola pengguna.');
    }
}
