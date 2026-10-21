{{--
    Form tambah/ubah akun internal. Variabel: $user (User baru atau yang diubah), $action (URL).
    Password tidak pernah ditampilkan; saat ubah, kosongkan jika tidak diganti.
--}}
@use('App\Enums\UserRole')

@php
    $diriSendiri = $user->exists && auth()->user()->is($user);
    $roleDipilih = old('role', $user->role?->value ?? UserRole::Staf->value);
@endphp

<form method="POST" action="{{ $action }}" class="bb-form" novalidate autocomplete="off">
    @csrf
    @if ($user->exists)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Data akun</h2>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                               class="form-control @error('name') is-invalid @enderror" maxlength="255" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Email (untuk login)</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                               class="form-control @error('email') is-invalid @enderror" maxlength="255" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <h2 class="h6 mb-2">{{ $user->exists ? 'Ganti password' : 'Password' }}</h2>
                    @if ($user->exists)
                        <p class="small text-body-secondary mb-3">Kosongkan jika password tidak diganti.</p>
                    @endif
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="password" class="form-label">Password baru</label>
                            <div class="input-group has-validation">
                                <input type="password" id="password" name="password" autocomplete="new-password"
                                       class="form-control @error('password') is-invalid @enderror" @required(! $user->exists)>
                                <button type="button" class="btn btn-outline-secondary bb-toggle-password" data-target="#password"
                                        aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="password_confirmation" class="form-label">Ulangi password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   autocomplete="new-password" class="form-control" @required(! $user->exists)>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card bb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-1">Role</h2>
                    <p class="small text-body-secondary mb-3">Menentukan halaman dan aksi yang boleh diakses (hierarki Admin &gt; Koordinator &gt; Staf).</p>

                    @if ($diriSendiri)
                        {{-- Role akun sendiri dikunci (divalidasi juga di server) agar Admin tidak mengunci dirinya. --}}
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                        <div class="alert alert-light border small mb-3">
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Role akun Anda sendiri tidak bisa diubah.
                        </div>
                    @endif

                    <fieldset @disabled($diriSendiri)>
                        <legend class="visually-hidden">Pilih role</legend>
                        <div class="d-grid gap-2">
                            @foreach (UserRole::cases() as $role)
                                <div>
                                    <input type="radio" class="btn-check" name="role" id="role-{{ $role->value }}" value="{{ $role->value }}"
                                           @checked($roleDipilih === $role->value)>
                                    <label class="bb-option w-100" for="role-{{ $role->value }}">
                                        <span class="badge bb-role bb-role-{{ $role->value }} align-self-start mt-1">L{{ $role->level() }}</span>
                                        <span>
                                            <span class="d-block fw-semibold">{{ $role->label() }}</span>
                                            <span class="d-block small text-body-secondary">{{ $role->deskripsi() }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('role')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('internal.users.index') }}" class="btn btn-link text-body-secondary">Batal</a>
        <button type="submit" class="btn btn-maroon px-4">
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ $user->exists ? 'Simpan Perubahan' : 'Buat Akun' }}
        </button>
    </div>
</form>
