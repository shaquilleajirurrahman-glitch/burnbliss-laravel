<?php

/*
|--------------------------------------------------------------------------
| Pengaturan toko Burn & Bliss
|--------------------------------------------------------------------------
| Nilai yang bisa berbeda per lingkungan dibaca dari .env (lihat .env.example).
*/

return [

    'alamat_toko' => 'Jl. Halimun Raya No.2, Setiabudi, Jakarta Selatan',

    'preorder' => [
        // Tanggal ambil/kirim paling cepat H+min_hari dan paling lambat H+maks_hari.
        'min_hari' => 1,
        'maks_hari' => 14,
        // Hari toko tutup (Carbon dayOfWeek: 0 = Minggu ... 6 = Sabtu).
        'hari_libur' => [0],
    ],

    // Kuota produksi (jumlah pcs) per tanggal jika admin belum mengaturnya di daily_quotas.
    'kuota_default' => (int) env('BB_KUOTA_DEFAULT', 30),

    'pembayaran' => [
        'bank' => env('BB_BANK_NAMA', 'BCA'),
        'nomor_rekening' => env('BB_BANK_NOREK', '0000000000'),
        'atas_nama' => env('BB_BANK_ATAS_NAMA', 'Burn & Bliss Bakery'),
        'qris_gambar' => 'images/qris.jpg', // relatif terhadap folder public/
    ],

    'bukti_bayar' => [
        // Disk privat (storage/app/private): bukti transfer memuat data rekening pelanggan,
        // jadi hanya bisa dibuka Admin/Koordinator lewat route internal.payments.proof.
        'disk' => 'local',
        'folder' => 'bukti',
        'maks_kb' => 2048,
    ],

];
