<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Dikonfirmasi = 'dikonfirmasi';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap Diambil/Dikirim',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Status tujuan yang sah dari status ini (tidak boleh loncat atau mundur sembarangan).
     * Satu-satunya langkah mundur: bukti bayar ditolak -> kembali Menunggu Pembayaran
     * agar pelanggan bisa mengunggah ulang.
     *
     * @return array<int, self>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::MenungguPembayaran => [self::MenungguVerifikasi, self::Dibatalkan],
            self::MenungguVerifikasi => [self::Dikonfirmasi, self::MenungguPembayaran, self::Dibatalkan],
            self::Dikonfirmasi => [self::Diproses, self::Dibatalkan],
            self::Diproses => [self::Siap],
            self::Siap => [self::Selesai],
            self::Selesai, self::Dibatalkan => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextAllowed(), true);
    }

    /**
     * Pelanggan hanya boleh mengunggah bukti bayar saat pesanan menunggu pembayaran.
     */
    public function menerimaBuktiBayar(): bool
    {
        return $this === self::MenungguPembayaran;
    }

    /**
     * Status yang menjadi antrean produksi (yang boleh dilihat & dikerjakan Staf).
     *
     * @return array<int, self>
     */
    public static function antreanProduksi(): array
    {
        return [self::Dikonfirmasi, self::Diproses, self::Siap];
    }

    public function isAntreanProduksi(): bool
    {
        return in_array($this, self::antreanProduksi(), true);
    }

    /**
     * Langkah produksi berikutnya: Dikonfirmasi -> Diproses -> Siap -> Selesai.
     */
    public function nextProduction(): ?self
    {
        return match ($this) {
            self::Dikonfirmasi => self::Diproses,
            self::Diproses => self::Siap,
            self::Siap => self::Selesai,
            default => null,
        };
    }

    /**
     * Label tombol untuk berpindah KE status ini di panel internal.
     */
    public function aksiLabel(): string
    {
        return match ($this) {
            self::Diproses => 'Mulai Produksi',
            self::Siap => 'Tandai Siap',
            self::Selesai => 'Selesaikan',
            default => $this->label(),
        };
    }
}
