@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    @include('dashboard._welcome', ['panel' => 'Admin Panel', 'fitur' => [
        'Ringkasan pesanan hari ini, menunggu verifikasi, dan omzet',
        'Kelola produk, varian, stok, dan kuota produksi harian',
        'Verifikasi bukti pembayaran',
        'Kelola pengguna dan role',
        'Laporan penjualan dan ekspor CSV',
    ]])
@endsection
