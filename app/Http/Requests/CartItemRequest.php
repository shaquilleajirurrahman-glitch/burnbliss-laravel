<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Validasi untuk POST /cart/add, /cart/update, dan /cart/remove.
 */
class CartItemRequest extends FormRequest
{
    /**
     * Keranjang terbuka untuk tamu (tanpa login).
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
            'variant_id' => ['required', 'integer', 'min:1'],
            // add: opsional (default 1) · update: wajib · remove: diabaikan
            'jumlah' => [
                $this->routeIs('cart.update') ? 'required' : 'sometimes',
                'integer',
                'min:1',
                'max:'.CartService::MAKS_PER_ITEM,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variant_id.required' => 'Produk belum dipilih.',
            'variant_id.integer' => 'Produk tidak valid.',
            'variant_id.min' => 'Produk tidak valid.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.integer' => 'Jumlah harus berupa angka.',
            'jumlah.min' => 'Jumlah minimal :min.',
            'jumlah.max' => 'Jumlah maksimal :max per varian.',
        ];
    }

    /**
     * Varian yang masih dijual (tidak dihapus) beserta produknya.
     *
     * @throws ValidationException
     */
    public function variant(): ProductVariant
    {
        $variant = ProductVariant::with('product')
            ->whereHas('product')
            ->find($this->integer('variant_id'));

        if (! $variant) {
            throw ValidationException::withMessages([
                'variant_id' => 'Produk tidak ditemukan atau sudah tidak dijual.',
            ]);
        }

        return $variant;
    }
}
