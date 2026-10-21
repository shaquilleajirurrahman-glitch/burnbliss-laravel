<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'metode',
    'jumlah',
    'status',
    'bukti_transfer',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'catatan',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'metode' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'jumlah' => 'integer',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Petugas yang memverifikasi/menandai lunas; tetap terbaca walau akunnya sudah dihapus.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh')->withTrashed();
    }

    /**
     * URL internal (butuh login + izin keuangan) untuk melihat file bukti di disk privat.
     */
    protected function buktiUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->bukti_transfer
            ? route('internal.payments.proof', $this)
            : null);
    }
}
