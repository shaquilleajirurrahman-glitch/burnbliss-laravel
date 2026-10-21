@extends('layouts.shop')

@section('title', 'Pembayaran '.$order->kode_pesanan)

@use('App\Enums\PaymentMethod')
@use('App\Support\Rupiah')

@section('content')
<section class="container py-5">
    @include('checkout._steps', ['aktif' => 3])

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card bb-card mb-4">
                <div class="card-body p-4 p-md-5">
                    <p class="bb-eyebrow text-body-secondary mb-1">Kode pesanan</p>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                        <span class="bb-kode">{{ $order->kode_pesanan }}</span>
                        <button type="button" class="btn btn-sm btn-outline-maroon js-copy" data-copy="{{ $order->kode_pesanan }}">
                            <i class="bi bi-copy me-1" aria-hidden="true"></i>Salin
                        </button>
                    </div>
                    <p class="text-body-secondary mb-1">Total tagihan</p>
                    <p class="bb-price display-6 mb-2">{{ Rupiah::format($order->total_harga) }}</p>
                    <p class="small text-body-secondary mb-0">
                        Simpan kode pesanan untuk
                        <a href="{{ route('track.index', ['kode' => $order->kode_pesanan]) }}" class="bb-link">melacak status pesanan</a>.
                    </p>
                </div>
            </div>

            @if (! $order->metode_pembayaran->butuhBukti())
                {{-- Tunai: tidak ada rekening/QRIS dan tidak ada form upload. --}}
                @include('checkout._cash-instruction')
            @elseif ($order->status->menerimaBuktiBayar())
                <div class="card bb-card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h5 mb-3">1. Bayar via {{ $order->metode_pembayaran->label() }}</h2>

                        @if ($order->metode_pembayaran === PaymentMethod::Transfer)
                            <dl class="bb-detail-list mb-0">
                                <dt>Bank</dt>
                                <dd>{{ $bank['bank'] }}</dd>
                                <dt>No. rekening</dt>
                                <dd>
                                    <span class="fw-semibold">{{ $bank['nomor_rekening'] }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline js-copy"
                                            data-copy="{{ $bank['nomor_rekening'] }}" aria-label="Salin nomor rekening">
                                        <i class="bi bi-copy"></i>
                                    </button>
                                </dd>
                                <dt>Atas nama</dt>
                                <dd>{{ $bank['atas_nama'] }}</dd>
                            </dl>
                        @else
                            <a href="{{ asset($bank['qris_gambar']) }}" target="_blank" rel="noopener" class="d-inline-block">
                                <img src="{{ asset($bank['qris_gambar']) }}" alt="QRIS Burn & Bliss Bakery"
                                     class="bb-qris rounded-3" width="220" loading="lazy">
                            </a>
                            <p class="small text-body-secondary mt-1 mb-0">Scan dari aplikasi bank/e-wallet. Ketuk untuk memperbesar.</p>
                        @endif

                        <p class="small text-body-secondary mt-3 mb-0">
                            Bayar <strong>tepat {{ Rupiah::format($order->total_harga) }}</strong> agar mudah kami cocokkan.
                        </p>
                    </div>
                </div>

                <div class="card bb-card">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h5 mb-3">2. Unggah bukti pembayaran</h2>

                        <form method="POST" action="{{ route('checkout.pembayaran.upload', $order) }}"
                              enctype="multipart/form-data" class="bb-form" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="bukti" class="form-label">Foto / screenshot bukti {{ $order->metode_pembayaran->label() }}</label>
                                <input type="file" id="bukti" name="bukti" accept="image/jpeg,image/png"
                                       data-max-kb="{{ config('burnbliss.bukti_bayar.maks_kb') }}"
                                       class="form-control js-bukti-input @error('bukti') is-invalid @enderror" required>
                                @error('bukti')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">JPG atau PNG, maksimal 2 MB.</div>
                                @enderror
                                <img src="" alt="Pratinjau bukti pembayaran" class="bb-bukti-preview rounded-3 mt-3 d-none js-bukti-preview">
                            </div>

                            <button type="submit" class="btn btn-maroon w-100 py-2">
                                <i class="bi bi-upload me-1" aria-hidden="true"></i>Kirim Bukti Pembayaran
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-success d-flex gap-2" role="status">
                    <i class="bi bi-check2-circle mt-1" aria-hidden="true"></i>
                    <div>
                        Bukti pembayaran untuk pesanan ini sudah kami terima. Status saat ini: <strong>{{ $order->status->label() }}</strong>.
                        <a href="{{ route('checkout.selesai', $order) }}" class="alert-link">Lihat ringkasan</a>
                    </div>
                </div>
            @endif
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
