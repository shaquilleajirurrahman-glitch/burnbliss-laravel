<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'status_lama', 'status_baru', 'keterangan', 'created_by'])]
class OrderStatusLog extends Model
{
    protected function casts(): array
    {
        return [
            'status_lama' => OrderStatus::class,
            'status_baru' => OrderStatus::class,
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
     * Petugas yang mengubah status; tetap terbaca walau akunnya sudah dihapus (soft delete).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
