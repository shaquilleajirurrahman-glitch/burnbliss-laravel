<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * CRUD akun internal (khusus Admin, lihat UserPolicy & routes/web.php).
 * Role tidak fillable, jadi selalu di-set eksplisit dari input yang sudah divalidasi.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $roleAktif = UserRole::tryFrom((string) $request->query('role'));

        return view('admin.users.index', [
            // Urut sesuai hierarki (Admin > Koordinator > Staf), lalu nama.
            'users' => User::query()
                ->when($roleAktif, fn ($query) => $query->where('role', $roleAktif))
                ->orderBy('name')
                ->get()
                ->sortBy(fn (User $user) => -$user->role->level())
                ->values(),
            'counts' => User::query()->toBase()->selectRaw('role, count(*) as jumlah')->groupBy('role')->pluck('jumlah', 'role'),
            'roleAktif' => $roleAktif,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email']));
        $user->password = Hash::make($request->validated('password'));
        $user->role = UserRole::from($request->validated('role'));
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('internal.users.index')
            ->with('status', "Akun {$user->name} ({$user->role->label()}) dibuat.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));
        $user->role = UserRole::from($request->validated('role'));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->validated('password'));
        }

        $user->save();

        return redirect()->route('internal.users.index')
            ->with('status', "Akun {$user->name} diperbarui".($request->filled('password') ? ' (password diganti).' : '.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user) {
            $user->delete(); // soft delete: tidak bisa login lagi, nama tetap ada di riwayat

            // Keluarkan dari semua sesi yang masih aktif (driver session: database).
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return redirect()->route('internal.users.index')
            ->with('status', "Akun {$user->name} dihapus dan tidak bisa login lagi.");
    }
}
