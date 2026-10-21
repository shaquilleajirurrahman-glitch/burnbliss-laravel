@extends('layouts.app')

@section('title', 'Kuota Produksi')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <p class="bb-eyebrow text-maroon mb-1">Panel Internal</p>
        <h1 class="bb-serif h2 mb-0">Kuota Produksi Harian</h1>
        <p class="small text-body-secondary mb-0 mt-1">
            Batas jumlah cheesecake (pcs) yang bisa dipesan per tanggal ambil/kirim.
            Tanggal yang belum diatur memakai kuota default <strong>{{ $kuotaDefault }} pcs</strong>.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card bb-card">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Atur kuota tanggal</h2>
                    <form method="POST" action="{{ route('internal.quotas.store') }}" class="bb-form" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input type="date" id="tanggal" name="tanggal" value="{{ old('tanggal') }}"
                                   min="{{ $tanggalMin->toDateString() }}"
                                   class="form-control @error('tanggal') is-invalid @enderror" required>
                            @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="kuota_maksimal" class="form-label">Kuota maksimal</label>
                            <div class="input-group has-validation">
                                <input type="number" id="kuota_maksimal" name="kuota_maksimal" value="{{ old('kuota_maksimal', $kuotaDefault) }}"
                                       min="1" max="1000" class="form-control @error('kuota_maksimal') is-invalid @enderror" required>
                                <span class="input-group-text">pcs</span>
                                @error('kuota_maksimal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-maroon w-100">Simpan Kuota</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="d-flex gap-2 mb-3">
                <a href="{{ route('internal.quotas.index') }}" @class(['bb-chip text-decoration-none', 'active' => ! $semua])>Mendatang</a>
                <a href="{{ route('internal.quotas.index', ['tampil' => 'semua']) }}" @class(['bb-chip text-decoration-none', 'active' => $semua])>Semua</a>
            </div>

            <div class="card bb-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 bb-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Kuota maksimal</th>
                                <th scope="col" class="text-end">Terpakai</th>
                                <th scope="col">Sisa</th>
                                <th scope="col" class="text-end"><span class="visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody id="quotaRows">
                            @forelse ($quotas as $quota)
                                @include('admin.quotas._row')
                            @empty
                                <tr class="js-quota-empty">
                                    <td colspan="5" class="text-center text-body-secondary py-5">
                                        <i class="bi bi-calendar2-week d-block fs-2 mb-2" aria-hidden="true"></i>
                                        Belum ada kuota khusus. Semua tanggal memakai kuota default {{ $kuotaDefault }} pcs.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="small text-body-secondary mt-2 mb-0">
                Kuota otomatis tercatat saat ada pesanan pada tanggal yang belum diatur. Kuota tidak bisa diturunkan di bawah jumlah yang sudah dipesan.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-quotas.js') }}?v={{ filemtime(public_path('js/admin-quotas.js')) }}"></script>
@endpush
