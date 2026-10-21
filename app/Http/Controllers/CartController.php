<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartItemRequest;
use App\Services\CartLine;
use App\Services\CartService;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Keranjang tamu. Endpoint POST mengembalikan JSON untuk request AJAX (jQuery),
 * dan redirect + flash message untuk form biasa (fallback tanpa JavaScript).
 * Kegagalan validasi/stok otomatis menjadi HTTP 422 { message, errors } untuk AJAX.
 */
class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View
    {
        $lines = $this->cart->lines();

        return view('cart.index', [
            'lines' => $lines,
            'total' => $this->cart->total($lines),
        ]);
    }

    public function add(CartItemRequest $request): JsonResponse|RedirectResponse
    {
        $variant = $request->variant();
        $this->cart->add($variant, $request->integer('jumlah', 1));

        return $this->respond($request, "{$variant->nama_varian} ditambahkan ke keranjang.", $variant->id);
    }

    public function update(CartItemRequest $request): JsonResponse|RedirectResponse
    {
        $variant = $request->variant();
        $this->cart->update($variant, $request->integer('jumlah'));

        return $this->respond($request, "Jumlah {$variant->nama_varian} diperbarui.", $variant->id);
    }

    public function remove(CartItemRequest $request): JsonResponse|RedirectResponse
    {
        $this->cart->remove($request->integer('variant_id'));

        return $this->respond($request, 'Item dihapus dari keranjang.');
    }

    private function respond(Request $request, string $message, ?int $variantId = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        $lines = $this->cart->lines();
        $total = $this->cart->total($lines);
        $line = $variantId ? $lines->first(fn (CartLine $l) => $l->variant->id === $variantId) : null;

        return response()->json([
            'success' => true,
            'message' => $message,
            'cart_count' => $lines->sum(fn (CartLine $l) => $l->jumlah),
            'total' => $total,
            'total_formatted' => Rupiah::format($total),
            'item' => $line?->toArray(),
        ]);
    }
}
