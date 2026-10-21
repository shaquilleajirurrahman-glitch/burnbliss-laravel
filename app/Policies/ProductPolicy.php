<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kelola produk & varian: khusus Admin (Koordinator & Staf mendapat 403).
 */
class ProductPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->admin($user);
    }

    public function create(User $user): Response
    {
        return $this->admin($user);
    }

    public function update(User $user, Product $product): Response
    {
        return $this->admin($user);
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->admin($user);
    }

    private function admin(User $user): Response
    {
        return $user->role === UserRole::Admin
            ? Response::allow()
            : Response::deny('Hanya Admin yang dapat mengelola produk.');
    }
}
