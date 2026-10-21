<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Support\Rupiah;

/**
 * Satu baris keranjang. Harga selalu dihitung dari database, bukan dari session.
 */
final readonly class CartLine
{
    public function __construct(
        public ProductVariant $variant,
        public int $jumlah,
    ) {}

    public function hargaSatuan(): int
    {
        return $this->variant->harga_akhir;
    }

    public function subtotal(): int
    {
        return $this->hargaSatuan() * $this->jumlah;
    }

    /**
     * Bentuk JSON untuk response AJAX.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variant->id,
            'nama' => $this->variant->nama_varian,
            'jumlah' => $this->jumlah,
            'stok' => $this->variant->stok,
            'harga_satuan' => $this->hargaSatuan(),
            'subtotal' => $this->subtotal(),
            'subtotal_formatted' => Rupiah::format($this->subtotal()),
        ];
    }
}
