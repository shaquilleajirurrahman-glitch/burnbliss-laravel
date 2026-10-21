{{-- Isi <tbody> tabel pesanan. Variabel: $orders (paginator), $keuangan (bool), $q (string). --}}
@forelse ($orders as $order)
    @include('admin.orders._row')
@empty
    <tr>
        <td colspan="{{ $keuangan ? 7 : 5 }}" class="text-center text-body-secondary py-5">
            <i class="bi bi-inbox d-block fs-2 mb-2" aria-hidden="true"></i>
            {{ $q !== '' ? 'Tidak ada pesanan yang cocok dengan pencarian.' : 'Belum ada pesanan di sini.' }}
        </td>
    </tr>
@endforelse
