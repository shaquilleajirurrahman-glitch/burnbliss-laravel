<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter laporan penjualan (halaman, AJAX, dan ekspor CSV memakai filter yang sama).
 * Default: awal bulan ini s.d. hari ini, semua varian.
 */
class SalesReportRequest extends FormRequest
{
    /**
     * Jika filter tidak valid (non-AJAX), kembali ke laporan default — bukan ke URL sebelumnya,
     * yang bisa saja URL bermasalah yang sama (redirect berulang).
     */
    protected $redirectRoute = 'internal.reports.index';

    /**
     * Hak akses dicek middleware can:viewFinancials (OrderPolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dari' => $this->filled('dari') ? $this->input('dari') : CarbonImmutable::today()->startOfMonth()->toDateString(),
            'sampai' => $this->filled('sampai') ? $this->input('sampai') : CarbonImmutable::today()->toDateString(),
            'varian' => $this->filled('varian') ? $this->input('varian') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'varian' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dari.date_format' => 'Format tanggal awal tidak valid.',
            'sampai.date_format' => 'Format tanggal akhir tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'varian.integer' => 'Varian tidak valid.',
            'varian.exists' => 'Varian tidak ditemukan.',
        ];
    }

    public function dari(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('dari'));
    }

    public function sampai(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('sampai'));
    }

    public function varianId(): ?int
    {
        $varian = $this->validated('varian');

        return $varian === null ? null : (int) $varian;
    }

    /**
     * Filter aktif untuk query string (link ekspor CSV, URL halaman).
     *
     * @return array<string, string|int>
     */
    public function filters(): array
    {
        return array_filter([
            'dari' => $this->validated('dari'),
            'sampai' => $this->validated('sampai'),
            'varian' => $this->varianId(),
        ], fn ($nilai) => $nilai !== null);
    }
}
