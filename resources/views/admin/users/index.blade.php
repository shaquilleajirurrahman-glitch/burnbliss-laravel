@extends('layouts.app')

@section('title', 'Kelola Pengguna')

@use('App\Enums\UserRole')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="bb-eyebrow text-maroon mb-1">Panel Internal</p>
            <h1 class="bb-serif h2 mb-0">Kelola Pengguna</h1>
            <p class="small text-body-secondary mb-0 mt-1">
                Akun internal yang bisa login. Pelanggan memesan sebagai tamu dan tidak memerlukan akun.
            </p>
        </div>
        <a href="{{ route('internal.users.create') }}" class="btn btn-maroon">
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Tambah Pengguna
        </a>
    </div>

    {{-- Tiga level pengguna (Ketentuan Tugas No. 3) beserta hak aksesnya. --}}
    <div class="row g-3 mb-4">
        @foreach (UserRole::cases() as $role)
            <div class="col-md-4">
                <a href="{{ route('internal.users.index', ['role' => $role->value]) }}"
                   @class(['card bb-card bb-role-card h-100 text-decoration-none', 'is-active' => $roleAktif === $role])>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bb-role bb-role-{{ $role->value }}">Level {{ $role->level() }} · {{ $role->label() }}</span>
                            <span class="fw-bold fs-5 text-body">{{ $counts[$role->value] ?? 0 }}</span>
                        </div>
                        <p class="small text-body-secondary mb-0">{{ $role->deskripsi() }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($roleAktif)
        <p class="small mb-2">
            Menampilkan role <strong>{{ $roleAktif->label() }}</strong> ·
            <a href="{{ route('internal.users.index') }}" class="bb-link">Tampilkan semua</a>
        </p>
    @endif

    <div class="card bb-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 bb-table">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php($diriSendiri = auth()->user()->is($user))
                        <tr id="user-{{ $user->id }}">
                            <td>
                                <span class="fw-semibold">{{ $user->name }}</span>
                                @if ($diriSendiri)
                                    <span class="badge bb-badge-role ms-1">Anda</span>
                                @endif
                            </td>
                            <td><a href="mailto:{{ $user->email }}" class="text-body-secondary">{{ $user->email }}</a></td>
                            <td><span class="badge bb-role bb-role-{{ $user->role->value }}">{{ $user->role->label() }}</span></td>
                            <td class="small text-body-secondary text-nowrap">{{ $user->created_at->locale('id')->translatedFormat('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('internal.users.edit', $user) }}" class="btn btn-sm btn-outline-maroon">
                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Ubah
                                    </a>
                                    @can('delete', $user)
                                        <form method="POST" action="{{ route('internal.users.destroy', $user) }}" class="js-confirm"
                                              data-confirm="Hapus akun {{ $user->name }} ({{ $user->email }})? Akun tidak bisa login lagi, tetapi namanya tetap tercatat di riwayat pesanan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link bb-remove">
                                                <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus
                                            </button>
                                        </form>
                                    @else
                                        <span class="small text-body-secondary px-2" title="Akun yang sedang dipakai login tidak bisa dihapus">—</span>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-5">Belum ada pengguna dengan role ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
