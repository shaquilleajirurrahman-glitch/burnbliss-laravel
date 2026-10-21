<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pesanan', 20)->unique();
            // Pelanggan memesan sebagai tamu (tanpa akun): data kontak disimpan di pesanan.
            $table->string('nama_pelanggan', 120);
            $table->string('nomor_hp', 20); // dinormalisasi: 08xxxxxxxxxx
            $table->unsignedInteger('total_harga');
            // Nilai harus sama dengan App\Enums\OrderStatus
            $table->enum('status', [
                'menunggu_pembayaran',
                'menunggu_verifikasi',
                'dikonfirmasi',
                'diproses',
                'siap',
                'selesai',
                'dibatalkan',
            ])->default('menunggu_pembayaran')->index();
            $table->text('alamat')->nullable(); // kosong jika ambil sendiri
            $table->enum('metode_pengiriman', ['ambil_sendiri', 'diantar']); // App\Enums\ShippingMethod
            $table->enum('metode_pembayaran', ['transfer', 'qris', 'cash']); // App\Enums\PaymentMethod
            $table->date('tanggal_ambil')->index();
            // Bukti bayar disimpan di payments.bukti_transfer (bisa upload ulang jika ditolak).
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
