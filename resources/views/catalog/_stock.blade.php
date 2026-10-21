{{-- Label stok varian. Variabel: $variant. --}}
@if ($variant->stok === 0)
    <span class="badge bb-stock bb-stock-out">Habis</span>
@elseif ($variant->stok <= 5)
    <span class="badge bb-stock bb-stock-low">Sisa {{ $variant->stok }}</span>
@else
    <span class="small text-body-secondary"><i class="bi bi-check2-circle text-maroon me-1"></i>Tersedia</span>
@endif
