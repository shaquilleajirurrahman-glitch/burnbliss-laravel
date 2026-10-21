@extends('layouts.app')

@section('title', 'Terlalu banyak percobaan')

@section('content')
<div class="container py-5 my-lg-5 text-center">
    <div class="card bb-card mx-auto" style="max-width: 32rem;">
        <div class="card-body p-5">
            <p class="bb-serif display-4 text-maroon mb-2">429</p>
            <h1 class="h4 mb-3">Terlalu banyak percobaan</h1>
            <p class="text-body-secondary mb-4">Demi keamanan, tunggu sekitar satu menit sebelum mencoba lagi.</p>
            <a href="{{ url()->previous() }}" class="btn btn-maroon">Kembali</a>
        </div>
    </div>
</div>
@endsection
