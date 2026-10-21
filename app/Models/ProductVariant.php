<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'nama_varian', 'gambar', 'stok', 'harga_tambahan'])]
class ProductVariant extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'harga_tambahan' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Harga jual varian = harga dasar produk + harga tambahan varian.
     */
    protected function hargaAkhir(): Attribute
    {
        return Attribute::get(fn (): int => $this->product->harga + $this->harga_tambahan);
    }

    /**
     * URL foto varian; jika varian tidak punya foto, pakai foto produk.
     */
    protected function gambarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->gambar ?? $this->product?->gambar;

            return $path ? Storage::disk('public')->url($path) : null;
        });
    }
}
