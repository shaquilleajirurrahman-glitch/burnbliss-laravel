{{-- Indikator langkah checkout. Variabel: $aktif (1-4). --}}
@php($langkah = ['Keranjang', 'Data Pemesan', 'Pembayaran', 'Selesai'])

<ol class="bb-steps list-unstyled d-flex flex-wrap gap-2 gap-md-4 mb-4" aria-label="Langkah pemesanan">
    @foreach ($langkah as $i => $nama)
        @php($nomor = $i + 1)
        <li @class(['bb-step', 'is-done' => $nomor < $aktif, 'is-active' => $nomor === $aktif])
            @if ($nomor === $aktif) aria-current="step" @endif>
            <span class="bb-step-dot">
                @if ($nomor < $aktif)
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                @else
                    {{ $nomor }}
                @endif
            </span>
            <span class="bb-step-label">{{ $nama }}</span>
        </li>
    @endforeach
</ol>
