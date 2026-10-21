<?php

namespace App\Providers;

use App\Services\CartService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Maks. 5 percobaan login per menit untuk tiap kombinasi email + IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        // Maks. 5 POST per menit per IP untuk tiap endpoint checkout (buat pesanan, verifikasi HP,
        // upload bukti) — dihitung terpisah agar salah ketik di satu form tidak memblokir form lain.
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->route()?->getName());
        });

        // Lacak pesanan: maks. 10 percobaan per menit per IP untuk mencegah tebak-tebakan kode/nomor HP.
        RateLimiter::for('lacak', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Terlalu banyak percobaan. Tunggu sekitar satu menit lalu coba lagi.',
                ], 429));
        });

        // Angka badge keranjang di navbar toko.
        View::composer('layouts.shop', function (ViewInstance $view) {
            $view->with('cartCount', app(CartService::class)->count());
        });
    }
}
