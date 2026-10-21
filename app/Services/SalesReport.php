<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data penjualan untuk dashboard, laporan, dan ekspor CSV.
 * Semua memakai definisi yang sama: Order::lunas() pada tanggal pesanan dibuat.
 */
class SalesReport
{
    /**
     * Baris laporan = item pesanan lunas dalam rentang tanggal, urut tanggal pesanan.
     *
     * @return Collection<int, OrderItem>
     */
    public function rows(CarbonInterface $dari, CarbonInterface $sampai, ?int $variantId = null): Collection
    {
        return OrderItem::query()
            ->with(['order', 'productVariant'])
            ->whereHas('order', fn (Builder $order) => $order->lunas()->dibuatAntara($dari, $sampai))
            ->when($variantId, fn (Builder $query) => $query->where('product_variant_id', $variantId))
            ->orderBy(Order::select('created_at')->whereColumn('orders.id', 'order_items.order_id'))
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, OrderItem>  $rows
     * @return array{jumlah_pesanan: int, total_pcs: int, total_pendapatan: int}
     */
    public function summary(Collection $rows): array
    {
        return [
            'jumlah_pesanan' => $rows->pluck('order_id')->unique()->count(),
            'total_pcs' => (int) $rows->sum('jumlah'),
            'total_pendapatan' => (int) $rows->sum('subtotal'),
        ];
    }

    /**
     * Omzet = total tagihan pesanan lunas dalam rentang tanggal.
     */
    public function omzet(CarbonInterface $dari, CarbonInterface $sampai): int
    {
        return (int) Order::query()->lunas()->dibuatAntara($dari, $sampai)->sum('total_harga');
    }
}
