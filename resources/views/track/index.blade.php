@extends('layouts.shop')

@section('title', 'Lacak Pesanan')

@section('content')
<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <header class="mb-4">
                <p class="bb-eyebrow text-maroon mb-2">Lacak Pesanan</p>
                <h1 class="bb-serif display-6 mb-2">Di mana cheesecake saya?</h1>
                <p class="text-body-secondary mb-0">Masukkan kode pesanan dan nomor HP yang Anda pakai saat memesan.</p>
            </header>

            {{-- Dikirim via AJAX oleh public/js/track.js (POST /lacak/cari -> JSON). --}}
            <form id="trackForm" method="POST" action="{{ route('track.search') }}" class="card bb-card mb-4" novalidate>
                @csrf
                <div class="card-body p-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="kode" class="form-label">Kode pesanan</label>
                            <input type="text" id="kode" name="kode" value="{{ $kode }}" maxlength="20"
                                   class="form-control bb-kode-input" placeholder="BNB-2026-XXXXXX" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nomor_hp" class="form-label">Nomor HP</label>
                            <input type="tel" id="nomor_hp" name="nomor_hp" maxlength="20" class="form-control"
                                   placeholder="0812 3456 7890" inputmode="tel" autocomplete="tel" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-maroon w-100">
                                <i class="bi bi-search me-1" aria-hidden="true"></i>Lacak
                            </button>
                        </div>
                    </div>
                    <p class="small text-body-secondary mb-0 mt-3">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                        Nomor HP memastikan hanya pemesan yang bisa melihat detail pesanan.
                    </p>
                </div>
            </form>

            <div id="trackError" class="alert alert-warning d-flex gap-2 d-none" role="alert">
                <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><span class="js-t-error"></span>
            </div>

            <div id="trackResult" class="d-none" aria-live="polite">
                <div class="card bb-card mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <p class="bb-eyebrow text-body-secondary mb-1">Kode pesanan</p>
                                <span class="bb-kode js-t-kode"></span>
                            </div>
                            <span class="bb-status js-t-status"></span>
                        </div>

                        <div class="alert alert-danger small d-none js-t-penolakan" role="alert">
                            <strong>Bukti pembayaran ditolak:</strong> <span class="js-t-catatan"></span>
                        </div>
                        <a href="#" class="btn btn-maroon mb-3 d-none js-t-bayar">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i><span class="js-t-bayar-label">Selesaikan pembayaran</span>
                        </a>

                        <dl class="bb-detail-list small mb-3">
                            <dt>Dibuat</dt><dd class="js-t-dibuat"></dd>
                            <dt>Pemesan</dt><dd class="js-t-pemesan"></dd>
                            <dt>Ambil/Kirim</dt><dd class="js-t-pengambilan"></dd>
                            <dt>Pembayaran</dt><dd class="js-t-pembayaran"></dd>
                        </dl>

                        <ul class="list-unstyled bb-summary-list small mb-2 js-t-items"></ul>
                        <div class="d-flex justify-content-between align-items-baseline border-top pt-2">
                            <span class="fw-semibold">Total</span>
                            <span class="bb-price fs-5 js-t-total"></span>
                        </div>
                    </div>
                </div>

                <div class="card bb-card">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-4">Perjalanan pesanan</h2>
                        <ol class="bb-timeline js-t-timeline" aria-label="Timeline status pesanan"></ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/track.js') }}?v={{ filemtime(public_path('js/track.js')) }}"></script>
@endpush
