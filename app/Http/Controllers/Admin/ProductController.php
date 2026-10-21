<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Product;
use App\Services\ProductManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD produk & varian (khusus Admin, lihat ProductPolicy & routes/web.php).
 */
class ProductController extends Controller
{
    public function __construct(private ProductManager $products) {}

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with(['variants' => fn ($query) => $query->orderBy('id')])->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', ['product' => new Product]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $product = $this->products->save(new Product, $request->validated(), $request->file('gambar'), $request->variantImages());

        return redirect()->route('internal.products.index')
            ->with('status', "Produk \"{$product->nama}\" ditambahkan dengan {$product->variants->count()} varian.");
    }

    public function edit(Product $product): View
    {
        $product->load(['variants' => fn ($query) => $query->orderBy('id')]);

        return view('admin.products.edit', ['product' => $product]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->products->save($product, $request->validated(), $request->file('gambar'), $request->variantImages());

        return redirect()->route('internal.products.index')
            ->with('status', "Produk \"{$product->nama}\" diperbarui.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('internal.products.index')
            ->with('status', "Produk \"{$product->nama}\" dihapus dan tidak lagi tampil di katalog.");
    }
}
