<?php

use App\Http\Controllers\Admin\DailyQuotaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TrackOrderController;
use Illuminate\Support\Facades\Route;

// ── Toko publik (pelanggan = tamu, tanpa login) ───────────────
Route::get('/', [ProductController::class, 'index'])->name('catalog.index');
Route::redirect('/menu', '/');

Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');

    // API JSON untuk AJAX jQuery (juga menerima form biasa sebagai fallback).
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/add', 'add')->name('add');
        Route::post('/update', 'update')->name('update');
        Route::post('/remove', 'remove')->name('remove');
    });
});

// ── Checkout tamu & pembayaran (publik, tanpa login) ──────────
// {order} = kode pesanan (Order::getRouteKeyName). POST dibatasi limiter "checkout" (5/menit).
Route::controller(CheckoutController::class)->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:checkout')->name('store');

    // Verifikasi nomor HP untuk membuka pesanan dari perangkat/session lain.
    Route::get('/{order}/akses', 'showAccess')->name('akses');
    Route::post('/{order}/akses', 'verifyAccess')->middleware('throttle:checkout')->name('akses.verify');

    Route::middleware('order.access')->group(function () {
        Route::get('/{order}/pembayaran', 'payment')->name('pembayaran');
        Route::post('/{order}/pembayaran', 'uploadProof')->middleware('throttle:checkout')->name('pembayaran.upload');
        Route::get('/{order}/selesai', 'success')->name('selesai');
    });
});

// ── Lacak pesanan (publik): kode pesanan + nomor HP. POST dibatasi 10/menit per IP. ──
Route::get('/lacak', [TrackOrderController::class, 'index'])->name('track.index');
Route::post('/lacak/cari', [TrackOrderController::class, 'search'])->middleware('throttle:lacak')->name('track.search');

// ── Login internal (admin, koordinator, staf). Tidak ada registrasi publik. ──
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// ── Area internal terproteksi (hierarki: admin > koordinator > staf) ──
// Atasan selalu boleh membuka halaman bawahannya. Navbar memakai UserRole::level()
// untuk aturan yang sama; tests/Feature/AuthRoleTest memastikan keduanya sinkron.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('/koordinator/dashboard', [DashboardController::class, 'koordinator'])
        ->middleware('role:admin,koordinator')
        ->name('koordinator.dashboard');

    Route::get('/staf/dashboard', [DashboardController::class, 'staf'])
        ->middleware('role:admin,koordinator,staf')
        ->name('staf.dashboard');

    // ── Kelola pesanan (semua role internal; isi & aksi dibatasi OrderPolicy) ──
    Route::middleware('role:admin,koordinator,staf')->prefix('internal')->name('internal.')->group(function () {
        Route::controller(AdminOrderController::class)->prefix('pesanan')->name('orders.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{order}', 'show')->middleware('can:viewFinancials,App\Models\Order')->name('show');
            Route::post('/{order}/verifikasi', 'verifyPayment')->middleware('can:verifyPayment,order')->name('verify');
            Route::post('/{order}/status', 'updateStatus')->middleware('can:updateStatus,order')->name('status');
            Route::post('/{order}/lunas', 'markCashPaid')->middleware('can:markCashPaid,order')->name('cash-paid');
        });

        // File bukti transfer dari disk privat — tidak ada URL publik.
        Route::get('/pembayaran/{payment}/bukti', [AdminOrderController::class, 'proof'])
            ->middleware('can:viewFinancials,App\Models\Order')
            ->name('payments.proof');

        // ── Ringkasan bisnis & laporan penjualan (Admin & Koordinator) ──
        Route::middleware('can:viewFinancials,App\Models\Order')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/laporan/export', [ReportController::class, 'export'])->name('reports.export');
        });

        // ── CRUD produk & varian (khusus Admin) ──
        Route::resource('produk', AdminProductController::class)
            ->except('show')
            ->parameters(['produk' => 'product'])
            ->names('products')
            ->middleware('can:viewAny,App\Models\Product');

        // ── Kuota produksi harian (Admin & Koordinator; hapus khusus Admin) ──
        Route::resource('kuota', DailyQuotaController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kuota' => 'quota'])
            ->names('quotas')
            ->middleware('can:viewAny,App\Models\DailyQuota')
            ->middlewareFor('destroy', 'can:delete,quota');

        // ── Kelola pengguna internal (khusus Admin; tidak bisa hapus akun sendiri) ──
        Route::resource('pengguna', AdminUserController::class)
            ->except('show')
            ->parameters(['pengguna' => 'user'])
            ->names('users')
            ->middleware('can:viewAny,App\Models\User')
            ->middlewareFor('destroy', 'can:delete,user');
    });
});
