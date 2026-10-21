<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Requests\UploadPaymentProofRequest;
use App\Http\Requests\VerifyOrderAccessRequest;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderAccess;
use App\Support\PreOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Checkout tamu: form data pemesan -> pesanan dibuat -> instruksi bayar & upload bukti -> selesai.
 * Halaman per pesanan dilindungi middleware "order.access" (lihat EnsureOrderAccess).
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CheckoutService $checkout,
        private OrderAccess $access,
    ) {}

    public function index(): View|RedirectResponse
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')
                ->withErrors(['keranjang' => 'Keranjang Anda masih kosong. Pilih produk dulu sebelum checkout.']);
        }

        return view('checkout.index', [
            'lines' => $lines,
            'total' => $this->cart->total($lines),
            'tanggalMin' => PreOrder::tanggalMin(),
            'tanggalMaks' => PreOrder::tanggalMaks(),
        ]);
    }

    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        $order = $this->checkout->placeOrder($request->validated());
        $this->access->grant($order);

        // Tunai tidak perlu unggah bukti: langsung ke halaman selesai berisi instruksi bayar di tempat.
        if (! $order->metode_pembayaran->butuhBukti()) {
            return redirect()
                ->route('checkout.selesai', $order)
                ->with('status', 'Pesanan berhasil dibuat dan sudah dikonfirmasi.');
        }

        return redirect()
            ->route('checkout.pembayaran', $order)
            ->with('status', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    public function payment(Order $order): View
    {
        $order->load(['items.productVariant.product', 'latestPayment']);

        return view('checkout.pembayaran', [
            'order' => $order,
            'bank' => config('burnbliss.pembayaran'),
        ]);
    }

    public function uploadProof(UploadPaymentProofRequest $request, Order $order): RedirectResponse
    {
        $this->checkout->uploadProof($order, $request->file('bukti'));

        return redirect()->route('checkout.selesai', $order);
    }

    public function success(Order $order): View|RedirectResponse
    {
        if ($order->status === OrderStatus::MenungguPembayaran) {
            return redirect()->route('checkout.pembayaran', $order);
        }

        $order->load(['items.productVariant.product', 'latestPayment']);

        return view('checkout.selesai', ['order' => $order]);
    }

    public function showAccess(Order $order): View|RedirectResponse
    {
        if ($this->access->allows($order)) {
            return redirect()->route('checkout.pembayaran', $order);
        }

        return view('checkout.akses', ['order' => $order]);
    }

    public function verifyAccess(VerifyOrderAccessRequest $request, Order $order): RedirectResponse
    {
        if (! $this->access->verify($order, $request->validated('nomor_hp'))) {
            return back()->withErrors(['nomor_hp' => 'Nomor HP tidak cocok dengan pesanan ini.']);
        }

        $this->access->grant($order);

        return redirect()->route('checkout.pembayaran', $order);
    }
}
