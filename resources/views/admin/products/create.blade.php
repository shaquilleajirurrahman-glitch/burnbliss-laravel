@extends('layouts.app')

@section('title', 'Tambah Produk')

@section('content')
<div class="container py-4 py-lg-5">
    <a href="{{ route('internal.products.index') }}" class="bb-link small d-inline-block mb-3">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke daftar produk
    </a>
    <h1 class="bb-serif h2 mb-4">Tambah Produk</h1>

    @include('admin.products._form', ['action' => route('internal.products.store')])
</div>
@endsection
