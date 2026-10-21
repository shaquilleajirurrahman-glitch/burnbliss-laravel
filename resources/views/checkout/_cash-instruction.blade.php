{{-- Instruksi pembayaran tunai (tanpa unggah bukti). Variabel: $order. --}}
@use('App\Enums\ShippingMethod')
@use('App\Support\PreOrder')
@use('App\Support\Rupiah')

@php($diantar = $order->metode_pengiriman === ShippingMethod::Diantar)

<div class="card bb-card bb-cash-card">
    <div class="card-body p-4 p-md-5">
        <h2 class="h5 mb-3">
            <i class="bi bi-cash-coin me-2 text-maroon" aria-hidden="true"></i>Bayar tunai saat {{ $diantar ? 'pesanan tiba' : 'pengambilan' }}
        </h2>
        <p class="mb-3">
            Silakan siapkan uang tunai sebesar <strong>{{ Rupiah::format($order->total_harga) }}</strong>
            saat {{ $diantar ? 'menerima pesanan' : 'mengambil pesanan di toko' }}.
        </p>
        <ul class="small text-body-secondary mb-0 ps-3">
            <li>Jadwal {{ $diantar ? 'pengiriman' : 'pengambilan' }}: {{ PreOrder::format($order->tanggal_ambil->toImmutable()) }}.</li>
            @if ($diantar)
                <li>Ongkos kirim ojek online dibayar terpisah langsung ke driver.</li>
            @else
                <li>Ambil di {{ config('burnbliss.alamat_toko') }} dengan menyebutkan kode pesanan.</li>
            @endif
            <li>Siapkan uang pas bila memungkinkan. Tidak perlu mengunggah bukti pembayaran.</li>
        </ul>
    </div>
</div>
