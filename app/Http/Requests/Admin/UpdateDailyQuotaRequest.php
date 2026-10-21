<?php

namespace App\Http\Requests\Admin;

use App\Models\DailyQuota;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyQuotaRequest extends FormRequest
{
    /**
     * Hak akses dicek middleware can:... (DailyQuotaPolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kuota tidak boleh diturunkan di bawah jumlah yang sudah dipesan (dicek ulang
     * dengan row lock di controller untuk menghindari balapan dengan checkout).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DailyQuota $quota */
        $quota = $this->route('quota');

        return [
            'kuota_maksimal' => ['required', 'integer', 'min:'.max(1, $quota->kuota_terpakai), 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kuota_maksimal.required' => 'Kuota wajib diisi.',
            'kuota_maksimal.integer' => 'Kuota harus berupa angka.',
            'kuota_maksimal.min' => 'Kuota tidak boleh kurang dari :min pcs (jumlah yang sudah dipesan untuk tanggal ini).',
            'kuota_maksimal.max' => 'Kuota maksimal :max pcs per hari.',
        ];
    }
}
