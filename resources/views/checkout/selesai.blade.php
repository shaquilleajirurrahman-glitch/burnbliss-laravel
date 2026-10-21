@extends('layouts.shop')

@section('title', 'Terima kasih')

@section('content')
@php($tunai = ! $order->metode_pembayaran->butuhBukti())

<section class="container py-5">
    @include('checkout._steps', ['aktif' => 4])

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card bb-card mb-4">
                <div class="card-body p-4 p-md-5">
                    <i class="bi bi-patch-check display-5 text-maroon" aria-hidden="true"></i>
                    <h1 class="bb-serif display-6 mt-3 mb-2">Terima kasih, {{ $order->nama_pelanggan }}!</h1>
                    <p class="text-body-secondary mb-4">
                        @if ($tunai)
                            Pesanan Anda sudah kami terima dan <strong>dikonfirmasi</strong>. Pembayaran dilakukan tunai saat serah terima.
                        @else
                            Bukti pembayaran Anda sudah kami terima dan sedang diverifikasi oleh tim Burn &amp; Bliss.
                        @endif
                    </p>

                    <p class="bb-eyebrow text-body-secondary mb-1">Kode pesanan</p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="bb-kode">{{ $order->kode_pesanan }}</span>
                        <button type="button" class="btn btn-sm btn-outline-maroon js-copy" data-copy="{{ $order->kode_pesanan }}">
                            <i class="bi bi-copy me-1" aria-hidden="true"></i>Salin
                        </button>
                    </div>
                </div>
            </div>

            @if ($tunai)
                <div class="mb-4">
                    @include('checkout._cash-instruction')
                </div>
            @endif

            <div class="card bb-card">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 mb-3">Langkah berikutnya</h2>
                    <ol class="bb-next-steps mb-0">
                        @unless ($tunai)
                            <li>Tim kami memverifikasi pembayaran, lalu status pesanan menjadi <strong>Dikonfirmasi</strong>.</li>
                        @endunless
                        <li>Cheesecake dipanggang sesuai tanggal pre-order Anda.</li>
                        <li>Status berubah menjadi <strong>Siap Diambil/Dikirim</strong> pada hari H.</li>
                        @if ($tunai)
                            <li>Bayar tunai saat serah terima, lalu pesanan <strong>Selesai</strong>.</li>
                        @endif
                    </ol>
                    <p class="small text-body-secondary mt-3 mb-4">
                        Simpan kode pesanan di atas.
                        @unless ($tunai)
                            Jika pembayaran tidak sesuai, Anda dapat mengunggah ulang bukti dari halaman pembayaran pesanan ini.
                        @endunless
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('track.index', ['kode' => $order->kode_pesanan]) }}" class="btn btn-maroon">
                            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Lacak Pesanan
                        </a>
                        <a href="{{ route('catalog.index') }}" class="btn btn-outline-maroon">Kembali ke Menu</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @include('checkout._order-summary')
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/checkout.js') }}?v={{ filemtime(public_path('js/checkout.js')) }}"></script>
@endpush
