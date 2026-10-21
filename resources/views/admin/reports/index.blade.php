@extends('layouts.app')

@section('title', 'Laporan Penjualan')

@section('content')
<div class="container py-4 py-lg-5" id="reportPage">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="bb-eyebrow text-maroon mb-1">Panel Internal</p>
            <h1 class="bb-serif h2 mb-0">Laporan Penjualan</h1>
            <p class="small text-body-secondary mb-0 mt-1">
                Hanya pesanan lunas (pembayaran diterima), berdasarkan tanggal pesanan dibuat.
            </p>
        </div>
        <a href="{{ $exportUrl }}" class="btn btn-maroon js-export">
            <i class="bi bi-download me-1" aria-hidden="true"></i>Ekspor CSV
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('internal.reports.index') }}" class="card bb-card mb-4" id="reportFilter" novalidate>
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="dari" class="form-label">Dari tanggal</label>
                    <input type="date" id="dari" name="dari" value="{{ $filter['dari'] }}" class="form-control" required>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="sampai" class="form-label">Sampai tanggal</label>
                    <input type="date" id="sampai" name="sampai" value="{{ $filter['sampai'] }}" class="form-control" required>
                </div>
                <div class="col-sm-8 col-lg-4">
                    <label for="varian" class="form-label">Varian</label>
                    <select id="varian" name="varian" class="form-select">
                        <option value="">Semua varian</option>
                        @foreach ($varian as $v)
                            <option value="{{ $v->id }}" @selected(($filter['varian'] ?? null) == $v->id)>
                                {{ $v->nama_varian }}{{ $v->trashed() ? ' (dihapus)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    {{-- Tanpa JS: tombol ini mengirim form biasa. Dengan JS: tabel diperbarui otomatis saat filter berubah. --}}
                    <button type="submit" class="btn btn-outline-maroon w-100">Terapkan</button>
                </div>
            </div>
        </div>
    </form>

    <div id="reportResult" aria-live="polite">
        @include('admin.reports._result')
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-reports.js') }}?v={{ filemtime(public_path('js/admin-reports.js')) }}"></script>
@endpush
