@extends('layouts.app')

@section('title', 'Ubah '.$user->name)

@section('content')
<div class="container py-4 py-lg-5">
    <a href="{{ route('internal.users.index') }}" class="bb-link small d-inline-block mb-3">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke daftar pengguna
    </a>
    <h1 class="bb-serif h2 mb-4">Ubah Pengguna</h1>

    @include('admin.users._form', ['action' => route('internal.users.update', $user)])
</div>
@endsection
