<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Support\NomorHp;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'kode_pesanan',
    'nama_pelanggan',
    'nomor_hp',
    'total_harga',
    'status',
    'alamat',
    'metode_pengiriman',
    'metode_pembayaran',
    'tanggal_ambil',
])]
class Order extends Model
{
    /**
     * URL publik memakai kode pesanan acak (bukan id berurutan), mis. /checkout/BNB-2026-K7QX9M/pembayaran.
     */
    public function getRouteKeyName(): string
    {
        return 'kode_pesanan';
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metode_pengiriman' => ShippingMethod::class,
            'metode_pembayaran' => PaymentMethod::class,
            'total_harga' => 'integer',
            'tanggal_ambil' => 'date',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Pembayaran (upload bukti) terakhir.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        // Urutan kedua by id: beberapa log bisa tercatat pada detik yang sama.
        return $this->hasMany(OrderStatusLog::class)->oldest()->oldest('id');
    }

    /**
     * Pesanan yang dihitung sebagai penjualan (dashboard omzet, laporan, CSV):
     * pembayarannya sudah diterima dan pesanan tidak dibatalkan — apa pun tahap produksinya.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function lunas(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Dibatalkan)
            ->whereHas('payments', fn (Builder $payment) => $payment->where('status', PaymentStatus::Diterima));
    }

    /**
     * Pesanan yang dibuat pada rentang tanggal (inklusif, waktu Jakarta).
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function dibuatAntara(Builder $query, CarbonInterface $dari, CarbonInterface $sampai): void
    {
        $query->whereBetween('created_at', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()]);
    }

    /**
     * Nomor HP untuk ditampilkan di halaman publik, mis. 0812****7890.
     */
    public function nomorHpTersamar(): string
    {
        return NomorHp::samarkan($this->nomor_hp);
    }
}
