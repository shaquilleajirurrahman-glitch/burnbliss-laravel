<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDailyQuotaRequest;
use App\Http\Requests\Admin\UpdateDailyQuotaRequest;
use App\Models\DailyQuota;
use App\Support\PreOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kuota produksi harian (Admin & Koordinator; hapus khusus Admin — lihat DailyQuotaPolicy).
 * Tambah lewat form biasa; ubah & hapus lewat AJAX (JSON) dari baris tabel.
 */
class DailyQuotaController extends Controller
{
    public function index(Request $request): View
    {
        $semua = $request->query('tampil') === 'semua';

        return view('admin.quotas.index', [
            'quotas' => DailyQuota::query()
                ->when(! $semua, fn ($query) => $query->where('tanggal', '>=', CarbonImmutable::today()->toDateString()))
                ->orderBy('tanggal', $semua ? 'desc' : 'asc')
                ->get(),
            'semua' => $semua,
            'kuotaDefault' => config('burnbliss.kuota_default'),
            'tanggalMin' => CarbonImmutable::today(),
        ]);
    }

    public function store(StoreDailyQuotaRequest $request): RedirectResponse
    {
        $quota = DailyQuota::create($request->validated() + ['kuota_terpakai' => 0]);

        return redirect()->route('internal.quotas.index')
            ->with('status', 'Kuota '.PreOrder::format($quota->tanggal->toImmutable())." diatur: {$quota->kuota_maksimal} pcs.");
    }

    public function update(UpdateDailyQuotaRequest $request, DailyQuota $quota): JsonResponse|RedirectResponse
    {
        $quota = DB::transaction(function () use ($request, $quota) {
            // Kunci baris: checkout yang berjalan bersamaan bisa menambah kuota_terpakai.
            $quota = DailyQuota::whereKey($quota->id)->lockForUpdate()->firstOrFail();
            $baru = (int) $request->validated('kuota_maksimal');

            if ($baru < $quota->kuota_terpakai) {
                throw ValidationException::withMessages([
                    'kuota_maksimal' => "Kuota tidak boleh kurang dari {$quota->kuota_terpakai} pcs (jumlah yang sudah dipesan untuk tanggal ini).",
                ]);
            }

            $quota->update(['kuota_maksimal' => $baru]);

            return $quota;
        });

        $pesan = 'Kuota '.PreOrder::format($quota->tanggal->toImmutable())." diubah menjadi {$quota->kuota_maksimal} pcs.";

        if (! $request->expectsJson()) {
            return back()->with('status', $pesan);
        }

        return response()->json([
            'success' => true,
            'message' => $pesan,
            'row' => view('admin.quotas._row', ['quota' => $quota])->render(),
        ]);
    }

    public function destroy(Request $request, DailyQuota $quota): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($quota) {
            $quota = DailyQuota::whereKey($quota->id)->lockForUpdate()->firstOrFail();

            if ($quota->kuota_terpakai > 0) {
                throw ValidationException::withMessages([
                    'kuota' => "Kuota tidak bisa dihapus karena sudah ada {$quota->kuota_terpakai} pcs dipesan untuk tanggal ini. Ubah nilainya saja.",
                ]);
            }

            $quota->delete();
        });

        $pesan = 'Kuota '.PreOrder::format($quota->tanggal->toImmutable()).' dihapus. Tanggal ini kembali memakai kuota default ('
            .config('burnbliss.kuota_default').' pcs).';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $pesan])
            : back()->with('status', $pesan);
    }
}
