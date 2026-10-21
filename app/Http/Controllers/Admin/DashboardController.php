<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Ringkasan bisnis untuk Admin & Koordinator (route dilindungi can:viewFinancials).
 */
class DashboardController extends Controller
{
    public function index(SalesReport $report): View
    {
        $hariIni = CarbonImmutable::today();

        return view('admin.dashboard', [
            'hariIni' => $hariIni,
            'pesananHariIni' => Order::whereDate('created_at', $hariIni)->count(),
            'menungguVerifikasi' => Order::where('status', OrderStatus::MenungguVerifikasi)->count(),
            'antreanProduksi' => Order::whereIn('status', OrderStatus::antreanProduksi())->count(),
            'omzetBulanIni' => $report->omzet($hariIni->startOfMonth(), $hariIni->endOfMonth()),
            'pelangganUnik' => Order::distinct()->count('nomor_hp'),
            'pesananTerbaru' => Order::latest()->latest('id')->limit(5)->get(),
        ]);
    }
}
