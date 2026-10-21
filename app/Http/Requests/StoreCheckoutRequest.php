<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\ShippingMethod;
use App\Support\NomorHp;
use App\Support\PreOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * Checkout terbuka untuk tamu (tanpa login).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nama_pelanggan' => trim((string) $this->input('nama_pelanggan')),
            'nomor_hp' => NomorHp::normalisasi((string) $this->input('nomor_hp')),
            'alamat' => trim((string) $this->input('alamat')) ?: null,
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
            'nama_pelanggan' => ['required', 'string', 'min:2', 'max:120'],
            'nomor_hp' => ['required', 'string', 'regex:/^08[1-9][0-9]{7,11}$/'],
            // Rule::in (bukan Rule::enum) agar pesan kustom ".in" di messages() terpakai;
            // Rules\Enum selalu memakai pesan bawaan validation.enum.
            'metode_pengiriman' => ['required', Rule::in(array_column(ShippingMethod::cases(), 'value'))],
            'alamat' => ['nullable', 'required_if:metode_pengiriman,'.ShippingMethod::Diantar->value, 'string', 'max:500'],
            'metode_pembayaran' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'tanggal_ambil' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.PreOrder::tanggalMin()->toDateString(),
                'before_or_equal:'.PreOrder::tanggalMaks()->toDateString(),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (PreOrder::hariLibur($value)) {
                        $fail('Toko tutup pada '.PreOrder::format($value).'. Silakan pilih hari lain.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_pelanggan.required' => 'Nama pemesan wajib diisi.',
            'nama_pelanggan.min' => 'Nama pemesan minimal :min karakter.',
            'nama_pelanggan.max' => 'Nama pemesan maksimal :max karakter.',
            'nomor_hp.required' => 'Nomor HP wajib diisi.',
            'nomor_hp.regex' => 'Nomor HP tidak valid. Gunakan nomor seluler, mis. 0812 3456 7890.',
            'metode_pengiriman.required' => 'Pilih metode pengambilan.',
            'metode_pengiriman.in' => 'Metode pengambilan tidak valid.',
            'alamat.required_if' => 'Alamat wajib diisi jika pesanan diantar.',
            'alamat.max' => 'Alamat maksimal :max karakter.',
            'metode_pembayaran.required' => 'Pilih metode pembayaran.',
            'metode_pembayaran.in' => 'Metode pembayaran tidak valid.',
            'tanggal_ambil.required' => 'Pilih tanggal ambil/kirim.',
            'tanggal_ambil.date_format' => 'Format tanggal tidak valid.',
            'tanggal_ambil.after_or_equal' => 'Pre-order paling cepat untuk '.PreOrder::format(PreOrder::tanggalMin()).'.',
            'tanggal_ambil.before_or_equal' => 'Pre-order paling lambat untuk '.PreOrder::format(PreOrder::tanggalMaks()).'.',
        ];
    }
}
