<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Keranjang belanja tamu (guest) yang disimpan di session Laravel.
 *
 * Isi session hanya `cart => [variant_id => jumlah]`. Nama, harga, dan stok selalu
 * dibaca ulang dari database, sehingga harga tidak bisa dimanipulasi dari sisi klien
 * dan perubahan harga oleh admin langsung berlaku.
 */
class CartService
{
    public const SESSION_KEY = 'cart';

    public const MAKS_PER_ITEM = 99;

    public function __construct(private Session $session) {}

    /**
     * @return array<int, int> variant_id => jumlah
     */
    public function raw(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * Total kuantitas semua item (angka di badge navbar).
     */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function quantity(int $variantId): int
    {
        return $this->raw()[$variantId] ?? 0;
    }

    public function has(int $variantId): bool
    {
        return array_key_exists($variantId, $this->raw());
    }

    /**
     * Tambah item; jika varian sudah ada, jumlahnya ditambahkan.
     *
     * @throws ValidationException jika melebihi stok
     */
    public function add(ProductVariant $variant, int $jumlah = 1): void
    {
        $baru = $this->quantity($variant->id) + $jumlah;

        $this->ensureStock($variant, $baru);
        $this->put($variant->id, $baru);
    }

    /**
     * Set jumlah item yang sudah ada di keranjang.
     *
     * @throws ValidationException jika item belum ada atau melebihi stok
     */
    public function update(ProductVariant $variant, int $jumlah): void
    {
        $this->ensureInCart($variant->id);
        $this->ensureStock($variant, $jumlah);
        $this->put($variant->id, $jumlah);
    }

    /**
     * @throws ValidationException jika item tidak ada di keranjang
     */
    public function remove(int $variantId): void
    {
        $this->ensureInCart($variantId);

        $cart = $this->raw();
        unset($cart[$variantId]);
        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Baris keranjang beserta data varian terbaru dari database. Item yang variannya
     * sudah dihapus admin otomatis dibuang dari session.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $raw = $this->raw();

        if ($raw === []) {
            return collect();
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($raw))
            ->whereHas('product')
            ->get()
            ->keyBy('id');

        $valid = array_intersect_key($raw, $variants->all());

        if (count($valid) !== count($raw)) {
            $this->session->put(self::SESSION_KEY, $valid);
        }

        return collect($valid)
            ->map(fn (int $jumlah, int $id) => new CartLine($variants[$id], $jumlah))
            ->values();
    }

    /**
     * @param  Collection<int, CartLine>|null  $lines  hasil lines() agar tidak query ulang
     */
    public function total(?Collection $lines = null): int
    {
        return ($lines ?? $this->lines())->sum(fn (CartLine $line) => $line->subtotal());
    }

    private function put(int $variantId, int $jumlah): void
    {
        $cart = $this->raw();
        $cart[$variantId] = $jumlah;
        $this->session->put(self::SESSION_KEY, $cart);
    }

    private function ensureInCart(int $variantId): void
    {
        if (! $this->has($variantId)) {
            throw ValidationException::withMessages([
                'variant_id' => 'Item tersebut tidak ada di keranjang.',
            ]);
        }
    }

    private function ensureStock(ProductVariant $variant, int $jumlah): void
    {
        if ($jumlah <= $variant->stok) {
            return;
        }

        throw ValidationException::withMessages([
            'jumlah' => $variant->stok === 0
                ? "Maaf, {$variant->nama_varian} sedang habis."
                : "Stok {$variant->nama_varian} hanya tersisa {$variant->stok}.",
        ]);
    }
}
