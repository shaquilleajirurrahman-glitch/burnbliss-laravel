<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOrderAccessRequest extends FormRequest
{
    /**
     * Terbuka untuk tamu; kecocokan nomor HP dicek di controller lewat OrderAccess.
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
            'nomor_hp' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_hp.required' => 'Masukkan nomor HP yang dipakai saat memesan.',
        ];
    }
}
