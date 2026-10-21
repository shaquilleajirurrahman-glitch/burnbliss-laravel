@extends('layouts.shop')

@section('title', 'Checkout')

@use('App\Enums\PaymentMethod')
@use('App\Enums\ShippingMethod')
@use('App\Support\PreOrder')
@use('App\Support\Rupiah')

@section('content')
@php($metodeDipilih = old('metode_pengiriman', ShippingMethod::AmbilSendiri->value))
@php($pembayaranDipilih = old('metode_pembayaran', PaymentMethod::Transfer->value))
@php($butuhBukti = (PaymentMethod::tryFrom((string) $pembayaranDipilih) ?? PaymentMethod::Transfer)->butuhBukti())

<section class="container py-5">
    @include('checkout._steps', ['aktif' => 2])
    <h1 class="bb-serif display-6 mb-4">Data Pemesan</h1>

    @error('keranjang')
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
            <div>{{ $message }} <a href="{{ route('cart.index') }}" class="alert-link">Buka keranjang</a></div>
        </div>
    @enderror

    <form method="POST" action="{{ route('checkout.store') }}" class="bb-form" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card bb-card">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="h5 mb-3">Kontak</h2>

                        <div class="mb-3">
                            <label for="nama_pelanggan" class="form-label">Nama lengkap</label>
                            <input type="text" id="nama_pelanggan" name="nama_pelanggan" value="{{ old('nama_pelanggan') }}"
                                   class="form-control @error('nama_pelanggan') is-invalid @enderror"
                                   autocomplete="name" maxlength="120" required>
                            @error('nama_pelanggan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="nomor_hp" class="form-label">Nomor HP / WhatsApp</label>
                            <input type="tel" id="nomor_hp" name="nomor_hp" value="{{ old('nomor_hp') }}"
                                   class="form-control @error('nomor_hp') is-invalid @enderror"
                                   inputmode="tel" autocomplete="tel" placeholder="0812 3456 7890" required>
                            @error('nomor_hp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">Dipakai untuk konfirmasi dan untuk membuka kembali pesanan Anda.</div>
                            @enderror
                        </div>

                        <h2 class="h5 mb-3">Pengambilan</h2>

                        <fieldset class="mb-3">
                            <legend class="form-label">Metode</legend>
                            <div class="row g-2">
                                @foreach (ShippingMethod::cases() as $metode)
                                    <div class="col-sm-6">
                                        <input type="radio" class="btn-check" name="metode_pengiriman"
                                               id="metode-{{ $metode->value }}" value="{{ $metode->value }}"
                                               @checked($metodeDipilih === $metode->value)>
                                        <label class="bb-option w-100" for="metode-{{ $metode->value }}">
                                            <i @class(['bi fs-4', 'bi-shop' => $metode === ShippingMethod::AmbilSendiri, 'bi-scooter' => $metode === ShippingMethod::Diantar]) aria-hidden="true"></i>
                                            <span>
                                                <span class="d-block fw-semibold">{{ $metode->label() }}</span>
                                                <span class="d-block small text-body-secondary">
                                                    {{ $metode === ShippingMethod::AmbilSendiri ? config('burnbliss.alamat_toko') : 'Ongkir dibayar ke driver saat tiba' }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('metode_pengiriman')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </fieldset>

                        <div @class(['mb-3 js-alamat', 'd-none' => $metodeDipilih !== ShippingMethod::Diantar->value])>
                            <label for="alamat" class="form-label">Alamat pengiriman</label>
                            <textarea id="alamat" name="alamat" rows="3" maxlength="500"
                                      class="form-control @error('alamat') is-invalid @enderror"
                                      autocomplete="street-address"
                                      placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, patokan">{{ old('alamat') }}</textarea>
                            @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="tanggal_ambil" class="form-label">Tanggal ambil / kirim</label>
                            <input type="date" id="tanggal_ambil" name="tanggal_ambil" value="{{ old('tanggal_ambil') }}"
                                   min="{{ $tanggalMin->toDateString() }}" max="{{ $tanggalMaks->toDateString() }}"
                                   class="form-control @error('tanggal_ambil') is-invalid @enderror" required>
                            @error('tanggal_ambil')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">
                                    Pre-order {{ PreOrder::format($tanggalMin) }} s.d. {{ PreOrder::format($tanggalMaks) }}.
                                    Toko tutup hari Minggu, dan kuota produksi terbatas per hari.
                                </div>
                            @enderror
                        </div>

                        <h2 class="h5 mt-4 mb-3">Pembayaran</h2>

                        <fieldset>
                            <legend class="visually-hidden">Metode pembayaran</legend>
                            <div class="row g-2">
                                @foreach (PaymentMethod::cases() as $bayar)
                                    <div class="col-12">
                                        <input type="radio" class="btn-check" name="metode_pembayaran"
                                               id="pembayaran-{{ $bayar->value }}" value="{{ $bayar->value }}"
                                               @checked($pembayaranDipilih === $bayar->value)>
                                        <label class="bb-option h-100 w-100" for="pembayaran-{{ $bayar->value }}">
                                            <i @class([
                                                'bi fs-4',
                                                'bi-bank' => $bayar === PaymentMethod::Transfer,
                                                'bi-qr-code' => $bayar === PaymentMethod::Qris,
                                                'bi-cash-coin' => $bayar === PaymentMethod::Cash,
                                            ]) aria-hidden="true"></i>
                                            <span>
                                                <span class="d-block fw-semibold">
                                                    {{ $bayar === PaymentMethod::Cash ? 'Tunai (bayar saat ambil/diantar)' : $bayar->label() }}
                                                </span>
                                                <span class="d-block small text-body-secondary">{{ $bayar->deskripsi() }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('metode_pembayaran')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </fieldset>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <aside class="card bb-card bb-summary">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Ringkasan Pesanan</h2>
                        <ul class="list-unstyled mb-0 bb-summary-list">
                            @foreach ($lines as $line)
                                <li class="d-flex justify-content-between gap-3">
                                    <span>{{ $line->variant->nama_varian }} <span class="text-body-secondary">× {{ $line->jumlah }}</span></span>
                                    <span>{{ Rupiah::format($line->subtotal()) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <hr>
                        <div class="d-flex justify-content-between align-items-baseline mb-3">
                            <span class="fw-semibold">Total</span>
                            <span class="bb-price fs-4">{{ Rupiah::format($total) }}</span>
                        </div>
                        <p @class(['small text-body-secondary js-ongkir-note', 'd-none' => $metodeDipilih !== ShippingMethod::Diantar->value])>
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Ongkos kirim ojek online dibayar langsung ke driver.
                        </p>
                        <button type="submit" class="btn btn-maroon w-100 py-2">Buat Pesanan</button>
                        <p class="small text-body-secondary text-center mt-2 mb-3">
                            <span @class(['js-next-upload', 'd-none' => ! $butuhBukti])>Berikutnya: instruksi pembayaran &amp; upload bukti.</span>
                            <span @class(['js-next-cash', 'd-none' => $butuhBukti])>Pesanan tunai langsung dikonfirmasi.</span>
                        </p>
                        <a href="{{ route('cart.index') }}" class="bb-link small"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Ubah keranjang</a>
                    </div>
                </aside>
            </div>
        </div>
    </form>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/checkout.js') }}?v={{ filemtime(public_path('js/checkout.js')) }}"></script>
@endpush
