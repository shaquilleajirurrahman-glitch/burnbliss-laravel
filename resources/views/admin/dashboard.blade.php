@extends('layouts.app')

@section('title', 'Dashboard')

@use('App\Support\PreOrder')
@use('App\Support\Rupiah')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="bb-eyebrow text-maroon mb-1">Ringkasan Bisnis</p>
            <h1 class="bb-serif h2 mb-0">Dashboard</h1>
            <p class="small text-body-secondary mb-0 mt-1">{{ PreOrder::format($hariIni) }}</p>
        </div>
        <a href="{{ route('internal.reports.index') }}" class="btn btn-outline-maroon">
            <i class="bi bi-file-earmark-bar-graph me-1" aria-hidden="true"></i>Laporan Penjualan
        </a>
    </div>

    {{-- Tata letak asimetris: omzet sebagai kartu utama, statistik operasional di grid 2x2. --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card bb-stat bb-stat-hero h-100">
                <div class="card-body p-4 p-lg-5 d-flex flex-column">
                    <p class="bb-stat-label mb-2">
                        <i class="bi bi-graph-up-arrow me-2" aria-hidden="true"></i>Omzet {{ $hariIni->locale('id')->translatedFormat('F Y') }}
                    </p>
                    <p class="bb-stat-value bb-serif mb-2" data-testid="omzet">{{ Rupiah::format($omzetBulanIni) }}</p>
                    <p class="small mb-0 opacity-75 mt-auto">Pesanan lunas: pembayaran diterima dan tidak dibatalkan.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-6">
                    <div class="card bb-card bb-stat h-100">
                        <div class="card-body p-4">
                            <p class="bb-stat-label mb-2"><i class="bi bi-bag-check me-2" aria-hidden="true"></i>Pesanan hari ini</p>
                            <p class="bb-stat-value mb-0" data-testid="pesanan-hari-ini">{{ $pesananHariIni }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <a href="{{ route('internal.orders.index', ['status' => 'menunggu_verifikasi']) }}"
                       @class(['card bb-card bb-stat h-100 text-decoration-none', 'bb-stat-alert' => $menungguVerifikasi > 0])>
                        <div class="card-body p-4">
                            <p class="bb-stat-label mb-2"><i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>Menunggu verifikasi</p>
                            <div class="d-flex align-items-baseline justify-content-between gap-2">
                                <p class="bb-stat-value mb-0" data-testid="menunggu-verifikasi">{{ $menungguVerifikasi }}</p>
                                <span class="small text-maroon fw-semibold">
                                    {{ $menungguVerifikasi > 0 ? 'Perlu ditinjau →' : 'Semua beres' }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-sm-6">
                    <div class="card bb-card bb-stat h-100">
                        <div class="card-body p-4">
                            <p class="bb-stat-label mb-2"><i class="bi bi-people me-2" aria-hidden="true"></i>Pelanggan unik</p>
                            <p class="bb-stat-value mb-0" data-testid="pelanggan-unik">{{ $pelangganUnik }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <a href="{{ route('internal.orders.index') }}" class="card bb-card bb-stat h-100 text-decoration-none">
                        <div class="card-body p-4">
                            <p class="bb-stat-label mb-2"><i class="bi bi-fire me-2" aria-hidden="true"></i>Antrean produksi</p>
                            <p class="bb-stat-value mb-0" data-testid="antrean-produksi">{{ $antreanProduksi }}</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card bb-card overflow-hidden">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 px-4">
            <h2 class="h6 mb-0">5 Pesanan Terbaru</h2>
            <a href="{{ route('internal.orders.index') }}" class="bb-link small">Lihat semua pesanan →</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 bb-table">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pesananTerbaru as $order)
                        <tr>
                            <td>
                                <a href="{{ route('internal.orders.index', ['q' => $order->kode_pesanan]) }}" class="bb-kode-sm text-decoration-none">
                                    {{ $order->kode_pesanan }}
                                </a>
                            </td>
                            <td>{{ $order->nama_pelanggan }}</td>
                            <td class="small text-body-secondary">{{ $order->created_at->locale('id')->translatedFormat('j M, H.i') }}</td>
                            <td class="text-end fw-semibold text-nowrap">{{ Rupiah::format($order->total_harga) }}</td>
                            <td>@include('admin.orders._status', ['status' => $order->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Belum ada pesanan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
