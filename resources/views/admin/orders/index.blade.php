@extends('layouts.app')

@section('title', $keuangan ? 'Kelola Pesanan' : 'Antrean Produksi')

@section('content')
<div class="container py-4 py-lg-5" id="ordersPage" data-list-url="{{ route('internal.orders.index') }}">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="bb-eyebrow text-maroon mb-1">Panel Internal</p>
            <h1 class="bb-serif h2 mb-0">{{ $keuangan ? 'Kelola Pesanan' : 'Antrean Produksi' }}</h1>
            @unless ($keuangan)
                <p class="small text-body-secondary mb-0 mt-1">Pesanan yang sudah dikonfirmasi dan perlu diproduksi, urut tanggal ambil terdekat.</p>
            @endunless
        </div>
        <div class="bb-search">
            <label for="searchOrders" class="visually-hidden">Cari pesanan</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" id="searchOrders" class="form-control" value="{{ $q }}"
                       placeholder="Cari kode, nama, atau no. HP" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter status pesanan">
        <button type="button" @class(['bb-chip js-filter', 'active' => ! $statusAktif]) data-status=""
                aria-pressed="{{ $statusAktif ? 'false' : 'true' }}">
            Semua <span class="bb-chip-count js-count" data-status="semua">{{ $counts['semua'] }}</span>
        </button>
        @foreach ($statusTersedia as $status)
            <button type="button" @class(['bb-chip js-filter', 'active' => $statusAktif === $status])
                    data-status="{{ $status->value }}" aria-pressed="{{ $statusAktif === $status ? 'true' : 'false' }}">
                {{ $status->label() }}
                <span class="bb-chip-count js-count" data-status="{{ $status->value }}">{{ $counts[$status->value] }}</span>
            </button>
        @endforeach
    </div>

    <div class="card bb-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 bb-table" id="ordersTable">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Ambil/Kirim</th>
                        @if ($keuangan)
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col">Pembayaran</th>
                        @endif
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="ordersBody" aria-live="polite">
                    @include('admin.orders._rows')
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <span class="small text-body-secondary" id="ordersInfo">{{ $info }}</span>
            <div id="ordersPagination">{{ $orders->links('admin.orders._pagination') }}</div>
        </div>
    </div>
</div>

@if ($keuangan)
    @include('admin.orders._modal')
@endif
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-orders.js') }}?v={{ filemtime(public_path('js/admin-orders.js')) }}"></script>
@endpush
