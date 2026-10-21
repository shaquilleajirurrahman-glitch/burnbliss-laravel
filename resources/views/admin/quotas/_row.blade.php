{{--
    Satu baris tabel kuota. Dipakai render awal & respons AJAX ubah kuota.
    Variabel: $quota (DailyQuota).
--}}
@use('App\Support\PreOrder')

@php
    $tanggal = $quota->tanggal->toImmutable();
    $lewat = $tanggal->isPast() && ! $tanggal->isToday();
    $persen = $quota->persenTerpakai();
@endphp

<tr id="quota-{{ $quota->id }}" @class(['bb-row-past' => $lewat])>
    <td>
        <div class="fw-semibold text-nowrap">{{ PreOrder::format($tanggal) }}</div>
        @if ($tanggal->isToday())
            <span class="badge bb-badge-role">Hari ini</span>
        @elseif ($lewat)
            <span class="small text-body-secondary">Sudah lewat</span>
        @endif
    </td>
    <td>
        @if ($lewat)
            <span class="fw-semibold">{{ $quota->kuota_maksimal }}</span> pcs
        @else
            <form method="POST" action="{{ route('internal.quotas.update', $quota) }}" class="d-flex gap-2 js-quota-update">
                @csrf
                @method('PUT')
                <div class="input-group input-group-sm bb-quota-input">
                    <input type="number" name="kuota_maksimal" value="{{ $quota->kuota_maksimal }}"
                           min="{{ max(1, $quota->kuota_terpakai) }}" max="1000" class="form-control"
                           aria-label="Kuota maksimal {{ PreOrder::format($tanggal) }}" required>
                    <span class="input-group-text">pcs</span>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-maroon">Simpan</button>
            </form>
        @endif
    </td>
    <td class="text-end">{{ $quota->kuota_terpakai }}</td>
    <td style="min-width: 12rem;">
        <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1 bb-progress" role="progressbar" aria-label="Kuota terpakai"
                 aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
                <div @class(['progress-bar', 'bb-progress-full' => $quota->sisa() === 0]) style="width: {{ $persen }}%"></div>
            </div>
            <span class="small text-nowrap {{ $quota->sisa() === 0 ? 'text-maroon fw-semibold' : 'text-body-secondary' }}">
                {{ $quota->sisa() === 0 ? 'Penuh' : 'Sisa '.$quota->sisa() }}
            </span>
        </div>
    </td>
    <td class="text-end">
        @can('delete', $quota)
            <form method="POST" action="{{ route('internal.quotas.destroy', $quota) }}" class="d-inline js-quota-delete"
                  data-confirm="Hapus kuota {{ PreOrder::format($tanggal) }}? Tanggal ini akan kembali memakai kuota default.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-link bb-remove" @disabled($quota->kuota_terpakai > 0)
                        title="{{ $quota->kuota_terpakai > 0 ? 'Tidak bisa dihapus: sudah ada pesanan untuk tanggal ini' : 'Hapus kuota' }}">
                    <i class="bi bi-trash3" aria-hidden="true"></i><span class="visually-hidden">Hapus</span>
                </button>
            </form>
        @endcan
    </td>
</tr>
