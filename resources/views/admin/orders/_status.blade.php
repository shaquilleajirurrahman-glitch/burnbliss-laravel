{{-- Badge status pesanan. Variabel: $status (App\Enums\OrderStatus). --}}
<span class="bb-status bb-status-{{ $status->value }}">{{ $status->label() }}</span>
