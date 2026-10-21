<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\OrderAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman pesanan tamu hanya boleh dibuka oleh session yang membuat pesanan itu
 * atau yang sudah memverifikasi nomor HP pemesan. Selain itu diarahkan ke form verifikasi.
 */
class EnsureOrderAccess
{
    public function __construct(private OrderAccess $access) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        if (! $order instanceof Order) {
            abort(404);
        }

        if (! $this->access->allows($order)) {
            return redirect()->route('checkout.akses', $order);
        }

        return $next($request);
    }
}
