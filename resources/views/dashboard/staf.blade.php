@extends('layouts.app')

@section('title', 'Dashboard Staf')

@section('content')
    @include('dashboard._welcome', ['panel' => 'Staf Panel', 'fitur' => [
        'Daftar pesanan masuk (pesanan tamu)',
        'Cek pesanan berdasarkan kode pesanan dan nomor HP',
        'Ubah status pesanan sesuai alur (Diproses → Siap Diambil/Dikirim → Selesai)',
        'Catat pengambilan pesanan di toko',
    ]])
@endsection
