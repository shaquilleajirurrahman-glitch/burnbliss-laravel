<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload bukti transfer/QRIS. Metode pembayaran diambil dari pesanan (dipilih saat checkout).
 */
class UploadPaymentProofRequest extends FormRequest
{
    /**
     * Hak akses ke pesanan sudah dicek middleware EnsureOrderAccess.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bukti' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('burnbliss.bukti_bayar.maks_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bukti.required' => 'Pilih foto/screenshot bukti pembayaran.',
            'bukti.file' => 'Bukti pembayaran gagal diunggah. Coba lagi.',
            'bukti.image' => 'Bukti pembayaran harus berupa gambar JPG atau PNG.',
            'bukti.mimes' => 'Bukti pembayaran harus berupa gambar JPG atau PNG.',
            'bukti.max' => 'Ukuran bukti pembayaran maksimal 2 MB.',
        ];
    }
}
