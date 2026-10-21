{{-- Ringkasan pesanan yang sudah dibuat. Variabel: $order (dengan items.productVariant.product). --}}
@use('App\Enums\ShippingMethod')
@use('App\Support\PreOrder')
@use('App\Support\Rupiah')

<aside class="card bb-card bb-summary">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Ringkasan Pesanan</h2>
            <span class="badge bb-badge-role">{{ $order->status->label() }}</span>
        </div>

        <dl class="bb-detail-list small mb-3">
            <dt>Pemesan</dt>
            <dd>{{ $order->nama_pelanggan }} · {{ $order->nomorHpTersamar() }}</dd>
            <dt>{{ $order->metode_pengiriman === ShippingMethod::Diantar ? 'Dikirim' : 'Diambil' }}</dt>
            <dd>{{ PreOrder::format($order->tanggal_ambil->toImmutable()) }}</dd>
            <dt>Metode</dt>
            <dd>{{ $order->metode_pengiriman->label() }}</dd>
            <dt>Pembayaran</dt>
            <dd>
                {{ $order->metode_pembayaran->label() }}
                @if ($order->latestPayment)
                    <span class="text-body-secondary">· {{ $order->latestPayment->status->label() }}</span>
                @endif
            </dd>
            @if ($order->alamat)
                <dt>Alamat</dt>
                <dd>{{ $order->alamat }}</dd>
            @endif
        </dl>

        <ul class="list-unstyled mb-0 bb-summary-list">
            @foreach ($order->items as $item)
                <li class="d-flex justify-content-between gap-3">
                    <span>{{ $item->productVariant->nama_varian }} <span class="text-body-secondary">× {{ $item->jumlah }}</span></span>
                    <span>{{ Rupiah::format($item->subtotal) }}</span>
                </li>
            @endforeach
        </ul>
        <hr>
        <div class="d-flex justify-content-between align-items-baseline">
            <span class="fw-semibold">Total</span>
            <span class="bb-price fs-4">{{ Rupiah::format($order->total_harga) }}</span>
        </div>
    </div>
</aside>
