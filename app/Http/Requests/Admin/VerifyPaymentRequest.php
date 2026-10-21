<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    /**
     * Hak akses dicek middleware can:verifyPayment (OrderPolicy).
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
            'keputusan' => ['required', 'in:terima,tolak'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keputusan.required' => 'Pilih terima atau tolak pembayaran.',
            'keputusan.in' => 'Keputusan verifikasi tidak valid.',
            'catatan.max' => 'Catatan maksimal :max karakter.',
        ];
    }

    public function diterima(): bool
    {
        return $this->validated('keputusan') === 'terima';
    }
}
