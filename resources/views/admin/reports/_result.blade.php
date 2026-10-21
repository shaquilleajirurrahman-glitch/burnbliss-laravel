{{--
    Ringkasan + tabel laporan. Dipakai render awal dan respons AJAX (diganti utuh via jQuery).
    Variabel: $rows (Collection<OrderItem>), $ringkasan (array), $periode (string).
--}}
@use('App\Support\Rupiah')

<div class="d-flex flex-wrap gap-2 mb-3 small">
    <span class="bb-chip"><i class="bi bi-calendar-range" aria-hidden="true"></i>{{ $periode }}</span>
    <span class="bb-chip">Pesanan <span class="bb-chip-count">{{ $ringkasan['jumlah_pesanan'] }}</span></span>
    <span class="bb-chip">Terjual <span class="bb-chip-count">{{ $ringkasan['total_pcs'] }} pcs</span></span>
</div>

<div class="card bb-card overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0 bb-table" id="reportTable">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Kode Pesanan</th>
                    <th scope="col">Nama Pelanggan</th>
                    <th scope="col">Varian</th>
                    <th scope="col" class="text-end">Jumlah Terjual</th>
                    <th scope="col" class="text-end">Total Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $item)
                    <tr>
                        <td class="text-nowrap">{{ $item->order->created_at->locale('id')->translatedFormat('j M Y') }}</td>
                        <td><span class="bb-kode-sm">{{ $item->order->kode_pesanan }}</span></td>
                        <td>{{ $item->order->nama_pelanggan }}</td>
                        <td>
                            {{ $item->productVariant->nama_varian }}
                            @if ($item->productVariant->trashed())
                                <span class="badge text-bg-light">dihapus</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $item->jumlah }}</td>
                        <td class="text-end text-nowrap">{{ Rupiah::format($item->subtotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-inbox d-block fs-2 mb-2" aria-hidden="true"></i>
                            Tidak ada penjualan lunas pada filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bb-grand-total">
                    <th scope="row" colspan="4">Grand Total</th>
                    <td class="text-end fw-bold">{{ $ringkasan['total_pcs'] }}</td>
                    <td class="text-end fw-bold text-nowrap" data-testid="grand-total">{{ Rupiah::format($ringkasan['total_pendapatan']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
