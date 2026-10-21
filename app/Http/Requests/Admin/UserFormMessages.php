<?php

namespace App\Http\Requests\Admin;

/**
 * Pesan validasi bersama untuk form tambah & ubah pengguna.
 */
final class UserFormMessages
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal :max karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal :max karakter.',
            'email.unique' => 'Email ini sudah dipakai akun lain (termasuk akun yang sudah dihapus).',
            'role.required' => 'Pilih role pengguna.',
            'role.in' => 'Role tidak valid. Pilih Admin, Koordinator, atau Staf.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal :min karakter.',
        ];
    }
}
