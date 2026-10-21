@extends('layouts.app')

@section('title', 'Dashboard Koordinator')

@section('content')
    @include('dashboard._welcome', ['panel' => 'Koordinator Panel', 'fitur' => [
        'Daftar pesanan yang perlu diproduksi',
        'Ubah status pesanan sesuai alur (Diproses → Siap Diambil/Dikirim → Selesai)',
        'Atur kuota produksi harian',
        'Pantau laporan produksi',
    ]])
@endsection
