<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tanggal', 'kuota_maksimal', 'kuota_terpakai'])]
class DailyQuota extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'kuota_maksimal' => 'integer',
            'kuota_terpakai' => 'integer',
        ];
    }

    public function sisa(): int
    {
        return max(0, $this->kuota_maksimal - $this->kuota_terpakai);
    }

    /**
     * Persentase kuota yang sudah terpakai (0-100), untuk progress bar.
     */
    public function persenTerpakai(): int
    {
        return $this->kuota_maksimal > 0
            ? (int) min(100, round($this->kuota_terpakai / $this->kuota_maksimal * 100))
            : 100;
    }
}
