<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\ProductVariant;
use App\Services\SalesReport;
use App\Support\Csv;
use App\Support\PreOrder;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan penjualan (route dilindungi can:viewFinancials). Hanya pesanan lunas
 * (Order::lunas), difilter berdasarkan tanggal pesanan dibuat & varian.
 */
class ReportController extends Controller
{
    public function __construct(private SalesReport $report) {}

    /**
     * Halaman laporan; request AJAX (Accept: application/json) mendapat JSON berisi tabel.
     */
    public function index(SalesReportRequest $request): View|JsonResponse
    {
        $rows = $this->report->rows($request->dari(), $request->sampai(), $request->varianId());
        $ringkasan = $this->report->summary($rows);

        $data = [
            'rows' => $rows,
            'ringkasan' => $ringkasan,
            'periode' => $this->periode($request),
            'filter' => $request->filters(),
            'exportUrl' => route('internal.reports.export', $request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.reports._result', $data)->render(),
                'export_url' => $data['exportUrl'],
                'ringkasan' => $ringkasan + ['total_pendapatan_formatted' => Rupiah::format($ringkasan['total_pendapatan'])],
            ]);
        }

        return view('admin.reports.index', $data + [
            // Varian yang sudah dihapus tetap bisa dipilih untuk melihat penjualan lamanya.
            'varian' => ProductVariant::withTrashed()->orderBy('id')->get(),
        ]);
    }

    /**
     * Unduh CSV sesuai filter yang sama dengan halaman laporan.
     */
    public function export(SalesReportRequest $request): StreamedResponse
    {
        $rows = $this->report->rows($request->dari(), $request->sampai(), $request->varianId());
        $ringkasan = $this->report->summary($rows);

        return response()->streamDownload(function () use ($rows, $ringkasan) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 dengan benar

            $tulis = fn (array $baris) => fputcsv($out, $baris, ',', '"', '');

            $tulis(['Tanggal', 'Kode Pesanan', 'Nama Pelanggan', 'Varian', 'Jumlah Terjual', 'Harga Satuan', 'Total Pendapatan']);
            foreach ($rows as $item) {
                $tulis([
                    $item->order->created_at->format('Y-m-d'),
                    $item->order->kode_pesanan,
                    Csv::aman($item->order->nama_pelanggan),
                    Csv::aman($item->productVariant->nama_varian),
                    $item->jumlah,
                    $item->harga_satuan,
                    $item->subtotal,
                ]);
            }
            $tulis(['GRAND TOTAL', '', '', '', $ringkasan['total_pcs'], '', $ringkasan['total_pendapatan']]);

            fclose($out);
        }, 'laporan-penjualan-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function periode(SalesReportRequest $request): string
    {
        return PreOrder::format($request->dari()).' – '.PreOrder::format($request->sampai());
    }
}
