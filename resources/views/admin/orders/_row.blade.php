{{--
    Satu baris tabel pesanan internal. Dipakai saat render awal, hasil filter AJAX, dan
    respons aksi (baris diganti via jQuery).
    Variabel: $order (dengan relasi latestPayment), $keuangan (bool: boleh lihat data pembayaran).
--}}
@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')
@use('App\Enums\PaymentStatus')
@use('App\Enums\ShippingMethod')
@use('App\Support\Rupiah')

@php
    $payment = $order->latestPayment;
    $next = $order->status->nextProduction();
    $bisaTandaiLunas = $order->metode_pembayaran === PaymentMethod::Cash
        && $payment?->status === PaymentStatus::MenungguPembayaran
        && in_array($order->status, [OrderStatus::Siap, OrderStatus::Selesai], true);
@endphp

<tr id="order-{{ $order->kode_pesanan }}" data-kode="{{ $order->kode_pesanan }}">
    <td>
        <span class="bb-kode-sm">{{ $order->kode_pesanan }}</span>
        <div class="small text-body-secondary">{{ $order->created_at->locale('id')->translatedFormat('j M, H.i') }}</div>
    </td>
    <td>
        <div class="fw-semibold">{{ $order->nama_pelanggan }}</div>
        <a href="tel:{{ $order->nomor_hp }}" class="small text-body-secondary">{{ $order->nomor_hp }}</a>
    </td>
    <td>
        <div>{{ $order->tanggal_ambil->locale('id')->translatedFormat('D, j M') }}</div>
        <div class="small text-body-secondary">
            @if ($order->metode_pengiriman === ShippingMethod::Diantar)
                <i class="bi bi-scooter me-1" aria-hidden="true"></i>Diantar
            @else
                <i class="bi bi-shop me-1" aria-hidden="true"></i>Ambil di toko
            @endif
        </div>
    </td>
    @if ($keuangan)
        <td class="text-end fw-semibold text-nowrap">{{ Rupiah::format($order->total_harga) }}</td>
        <td>
            <div class="small">{{ $order->metode_pembayaran->label() }}</div>
            @if ($payment)
                <span class="bb-pay bb-pay-{{ $payment->status->value }}">{{ $payment->status->label() }}</span>
            @else
                <span class="bb-pay">Belum ada bukti</span>
            @endif
        </td>
    @endif
    <td>@include('admin.orders._status', ['status' => $order->status])</td>
    <td class="text-end">
        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
            @if ($order->status === OrderStatus::MenungguVerifikasi)
                @can('verifyPayment', $order)
                    <button type="button" class="btn btn-sm btn-maroon js-open-detail"
                            data-url="{{ route('internal.orders.show', $order) }}">
                        <i class="bi bi-image me-1" aria-hidden="true"></i>Lihat Bukti
                    </button>
                @endcan
            @endif

            @if ($next)
                @can('updateStatus', $order)
                    <button type="button" class="btn btn-sm btn-outline-maroon js-order-action"
                            data-url="{{ route('internal.orders.status', $order) }}"
                            data-payload='{"status":"{{ $next->value }}"}'
                            data-confirm="Ubah status {{ $order->kode_pesanan }} menjadi {{ $next->label() }}?">
                        {{ $next->aksiLabel() }}
                    </button>
                @endcan
            @endif

            @if ($bisaTandaiLunas)
                @can('markCashPaid', $order)
                    <button type="button" class="btn btn-sm btn-outline-maroon js-order-action"
                            data-url="{{ route('internal.orders.cash-paid', $order) }}"
                            data-payload="{}"
                            data-confirm="Tandai pembayaran tunai {{ $order->kode_pesanan }} sebesar {{ Rupiah::format($order->total_harga) }} sudah diterima?">
                        <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Tandai Lunas (Tunai)
                    </button>
                @endcan
            @endif

            @if ($keuangan && $order->status !== OrderStatus::MenungguVerifikasi)
                <button type="button" class="btn btn-sm btn-link text-body-secondary js-open-detail"
                        data-url="{{ route('internal.orders.show', $order) }}">Detail</button>
            @endif
        </div>
    </td>
</tr>
