<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyQuotaRequest extends FormRequest
{
    /**
     * Hak akses dicek middleware can:... (DailyQuotaPolicy).
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
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', Rule::unique('daily_quotas', 'tanggal')],
            'kuota_maksimal' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal.required' => 'Pilih tanggal.',
            'tanggal.date_format' => 'Format tanggal tidak valid.',
            'tanggal.after_or_equal' => 'Kuota hanya bisa diatur untuk hari ini atau tanggal mendatang.',
            'tanggal.unique' => 'Kuota untuk tanggal ini sudah diatur. Ubah nilainya di tabel.',
            'kuota_maksimal.required' => 'Kuota wajib diisi.',
            'kuota_maksimal.integer' => 'Kuota harus berupa angka.',
            'kuota_maksimal.min' => 'Kuota minimal :min pcs.',
            'kuota_maksimal.max' => 'Kuota maksimal :max pcs per hari.',
        ];
    }
}
