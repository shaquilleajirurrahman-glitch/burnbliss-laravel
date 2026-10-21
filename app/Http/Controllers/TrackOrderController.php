<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackOrderRequest;
use App\Models\Order;
use App\Services\OrderAccess;
use App\Services\OrderTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lacak pesanan untuk tamu: kode pesanan + nomor HP pemesan (POST dibatasi limiter "lacak").
 */
class TrackOrderController extends Controller
{
    private const TIDAK_DITEMUKAN = 'Pesanan tidak ditemukan. Periksa kembali Kode dan Nomor HP Anda.';

    public function __construct(
        private OrderAccess $access,
        private OrderTimeline $timeline,
    ) {}

    public function index(Request $request): View
    {
        return view('track.index', [
            // Bisa diisi otomatis dari link, mis. /lacak?kode=BNB-2026-K7QX9M.
            'kode' => mb_strtoupper(mb_substr(trim((string) $request->query('kode')), 0, 20)),
        ]);
    }

    /**
     * JSON pesanan + timeline. Kode salah dan nomor HP salah memberi respons yang sama (404),
     * agar tidak bisa dipakai untuk menebak kode pesanan yang valid.
     */
    public function search(TrackOrderRequest $request): JsonResponse
    {
        $order = Order::where('kode_pesanan', $request->validated('kode'))->first();

        if (! $order || ! $this->access->verify($order, $request->validated('nomor_hp'))) {
            return response()->json(['message' => self::TIDAK_DITEMUKAN], 404);
        }

        // Pemilik sudah terbukti (kode + nomor HP), jadi boleh membuka halaman pembayaran pesanan ini
        // (mis. untuk mengunggah ulang bukti yang ditolak) tanpa verifikasi ulang.
        $this->access->grant($order);

        return response()->json($this->timeline->toArray($order));
    }
}
