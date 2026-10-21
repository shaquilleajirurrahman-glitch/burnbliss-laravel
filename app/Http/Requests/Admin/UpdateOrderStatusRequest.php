<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Hak akses dicek middleware can:updateStatus (OrderPolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Endpoint ini hanya untuk langkah produksi; urutannya divalidasi di OrderWorkflow.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                OrderStatus::Diproses->value,
                OrderStatus::Siap->value,
                OrderStatus::Selesai->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status tujuan wajib diisi.',
            'status.in' => 'Status tujuan harus salah satu langkah produksi: Diproses, Siap, atau Selesai.',
        ];
    }

    public function target(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
