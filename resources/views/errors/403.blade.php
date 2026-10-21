@extends('layouts.app')

@section('title', 'Akses ditolak')

@section('content')
<div class="container py-5 my-lg-5 text-center">
    <div class="card bb-card mx-auto" style="max-width: 32rem;">
        <div class="card-body p-5">
            <p class="bb-serif display-4 text-maroon mb-2">403</p>
            <h1 class="h4 mb-3">Akses ditolak</h1>
            <p class="text-body-secondary mb-4">{{ $exception->getMessage() ?: 'Anda tidak memiliki akses ke halaman ini.' }}</p>
            @auth
                <a href="{{ route(auth()->user()->role->dashboardRoute()) }}" class="btn btn-maroon">Kembali ke dashboard saya</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-maroon">Masuk</a>
            @endauth
        </div>
    </div>
</div>
@endsection
