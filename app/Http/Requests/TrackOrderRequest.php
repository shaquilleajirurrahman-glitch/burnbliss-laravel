<?php

namespace App\Http\Requests;

use App\Support\NomorHp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    /**
     * Halaman lacak terbuka untuk tamu.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode' => mb_strtoupper(trim((string) $this->input('kode'))),
            'nomor_hp' => NomorHp::normalisasi((string) $this->input('nomor_hp')),
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
            'kode' => ['required', 'string', 'max:20'],
            'nomor_hp' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Masukkan kode pesanan.',
            'kode.max' => 'Kode pesanan tidak valid.',
            'nomor_hp.required' => 'Masukkan nomor HP yang dipakai saat memesan.',
            'nomor_hp.max' => 'Nomor HP tidak valid.',
        ];
    }
}
