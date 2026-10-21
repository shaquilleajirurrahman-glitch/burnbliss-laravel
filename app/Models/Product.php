<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['nama', 'deskripsi', 'harga', 'gambar'])]
class Product extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'harga' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected function gambarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->gambar ? Storage::disk('public')->url($this->gambar) : null);
    }
}
