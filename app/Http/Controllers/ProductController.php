<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Katalog publik: semua produk beserta variannya.
     */
    public function index(CartService $cart): View
    {
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->orderBy('id'), 'variants.product'])
            ->whereHas('variants')
            ->orderBy('id')
            ->get();

        return view('catalog.index', [
            'products' => $products,
            'diKeranjang' => $cart->raw(),
        ]);
    }
}
