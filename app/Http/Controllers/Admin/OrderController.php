<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Requests\Admin\VerifyPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderWorkflow;
use App\Support\NomorHp;
use App\Support\PreOrder;
use App\Support\Rupiah;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kelola pesanan di panel internal. Hak akses per aksi diatur OrderPolicy lewat middleware
 * "can:" di routes/web.php; isi daftar dibatasi sesuai izin (Staf hanya antrean produksi).
 * Endpoint aksi mengembalikan JSON berisi HTML baris terbaru untuk diganti via jQuery.
 */
class OrderController extends Controller
{
    private const PER_HALAMAN = 15;

    public function __construct(private OrderWorkflow $workflow) {}

    /**
     * Halaman daftar pesanan; request AJAX (Accept: application/json) mendapat JSON berisi baris tabel.
     */
    public function index(Request $request): View|JsonResponse
    {
        $keuangan = $this->bolehLihatKeuangan($request);
        $statusTersedia = $keuangan ? OrderStatus::cases() : OrderStatus::antreanProduksi();

        $statusAktif = OrderStatus::tryFrom((string) $request->query('status'));
        if ($statusAktif && ! in_array($statusAktif, $statusTersedia, true)) {
            $statusAktif = null; // Staf tidak boleh memfilter status di luar antrean produksi.
        }

        $q = mb_substr(trim((string) $request->query('q')), 0, 50);

        $orders = $this->visibleOrders($request)
            ->with('latestPayment')
            ->when($statusAktif, fn (Builder $query) => $query->where('status', $statusAktif))
            ->when($q !== '', fn (Builder $query) => $this->search($query, $q))
            ->when(
                $keuangan,
                fn (Builder $query) => $query->latest('id'),
                fn (Builder $query) => $query->orderBy('tanggal_ambil')->orderBy('id'), // antrean: yang paling dekat dulu
            )
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        $data = [
            'orders' => $orders,
            'keuangan' => $keuangan,
            'statusTersedia' => $statusTersedia,
            'statusAktif' => $statusAktif,
            'q' => $q,
            'counts' => $this->counts($request),
            'info' => $this->info($orders),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.orders._rows', $data)->render(),
                'pagination' => $orders->links('admin.orders._pagination')->toHtml(),
                'info' => $data['info'],
                'counts' => $data['counts'],
            ]);
        }

        return view('admin.orders.index', $data);
    }

    /**
     * Detail pesanan + pembayaran terakhir untuk modal "Lihat Bukti" (khusus Admin/Koordinator).
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.productVariant', 'latestPayment.verifier']);
        $payment = $order->latestPayment;

        return response()->json([
            'kode' => $order->kode_pesanan,
            'nama' => $order->nama_pelanggan,
            'nomor_hp' => $order->nomor_hp,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'pengambilan' => $order->metode_pengiriman->label().' · '.PreOrder::format($order->tanggal_ambil->toImmutable()),
            'alamat' => $order->alamat,
            'metode_pembayaran' => $order->metode_pembayaran->label(),
            'total_formatted' => Rupiah::format($order->total_harga),
            'items' => $order->items->map(fn ($item) => [
                'nama' => $item->productVariant->nama_varian,
                'jumlah' => $item->jumlah,
                'subtotal_formatted' => Rupiah::format($item->subtotal),
            ]),
            'payment' => $payment ? [
                'metode' => $payment->metode->label(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'jumlah_formatted' => Rupiah::format($payment->jumlah),
                'dibuat' => $payment->created_at->locale('id')->translatedFormat('j M Y, H.i'),
                'bukti_url' => $payment->bukti_url,
                'verifikator' => $payment->verifier?->name,
                'catatan' => $payment->catatan,
            ] : null,
            'bisa_verifikasi' => $order->status === OrderStatus::MenungguVerifikasi,
            'verify_url' => route('internal.orders.verify', $order),
        ]);
    }

    public function verifyPayment(VerifyPaymentRequest $request, Order $order): JsonResponse
    {
        $order = $this->workflow->verifyPayment($order, $request->user(), $request->diterima(), $request->validated('catatan'));

        return $this->rowResponse($request, $order, $request->diterima()
            ? "Pembayaran {$order->kode_pesanan} diterima. Pesanan masuk antrean produksi."
            : "Pembayaran {$order->kode_pesanan} ditolak. Pelanggan dapat mengunggah ulang bukti.");
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->workflow->advanceProduction($order, $request->target(), $request->user());

        return $this->rowResponse($request, $order, "Status {$order->kode_pesanan} sekarang: {$order->status->label()}.");
    }

    public function markCashPaid(Request $request, Order $order): JsonResponse
    {
        $order = $this->workflow->markCashPaid($order, $request->user());

        return $this->rowResponse($request, $order, "Pembayaran tunai {$order->kode_pesanan} ditandai lunas.");
    }

    /**
     * Tampilkan file bukti pembayaran dari disk privat (route dilindungi auth + can:viewFinancials).
     */
    public function proof(Payment $payment): Response
    {
        $disk = Storage::disk(config('burnbliss.bukti_bayar.disk'));

        abort_unless($payment->bukti_transfer && $disk->exists($payment->bukti_transfer), 404);

        return $disk->response($payment->bukti_transfer, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Pesanan yang boleh dilihat user ini: Staf hanya antrean produksi.
     *
     * @return Builder<Order>
     */
    private function visibleOrders(Request $request): Builder
    {
        return Order::query()->when(
            ! $this->bolehLihatKeuangan($request),
            fn (Builder $query) => $query->whereIn('status', OrderStatus::antreanProduksi()),
        );
    }

    private function bolehLihatKeuangan(Request $request): bool
    {
        return $request->user()->can('viewFinancials', Order::class);
    }

    /**
     * Cari berdasarkan kode pesanan, nama, atau nomor HP.
     *
     * @param  Builder<Order>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $hp = NomorHp::normalisasi($q);

        $query->where(function (Builder $where) use ($like, $hp) {
            $where->where('kode_pesanan', 'like', $like)
                ->orWhere('nama_pelanggan', 'like', $like)
                ->when(strlen($hp) >= 4, fn (Builder $w) => $w->orWhere('nomor_hp', 'like', '%'.$hp.'%'));
        });
    }

    /**
     * Jumlah pesanan per status (untuk chip filter) + total yang terlihat.
     *
     * @return array<string, int>
     */
    private function counts(Request $request): array
    {
        $perStatus = $this->visibleOrders($request)
            ->toBase()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $counts = ['semua' => (int) $perStatus->sum()];
        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] = (int) ($perStatus[$status->value] ?? 0);
        }

        return $counts;
    }

    private function info(LengthAwarePaginator $orders): string
    {
        return $orders->total() === 0
            ? 'Tidak ada pesanan.'
            : "Menampilkan {$orders->firstItem()}–{$orders->lastItem()} dari {$orders->total()} pesanan";
    }

    private function rowResponse(Request $request, Order $order, string $message): JsonResponse
    {
        $order->load('latestPayment');

        return response()->json([
            'success' => true,
            'message' => $message,
            'order' => [
                'kode' => $order->kode_pesanan,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
            ],
            'row' => view('admin.orders._row', [
                'order' => $order,
                'keuangan' => $this->bolehLihatKeuangan($request),
            ])->render(),
            'counts' => $this->counts($request),
        ]);
    }
}
