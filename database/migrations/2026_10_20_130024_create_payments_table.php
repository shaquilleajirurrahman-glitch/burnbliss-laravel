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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('metode', ['transfer', 'qris', 'cash']); // App\Enums\PaymentMethod
            $table->unsignedInteger('jumlah');
            // App\Enums\PaymentStatus
            $table->enum('status', ['menunggu_pembayaran', 'menunggu_verifikasi', 'diterima', 'ditolak'])
                ->default('menunggu_verifikasi');
            // Path di disk privat "local" (storage/app/private/bukti), hanya bisa dibuka lewat
            // route internal yang dilindungi auth + policy. Null untuk tunai.
            $table->string('bukti_transfer')->nullable();
            // Audit verifikasi: siapa yang menerima/menolak (atau menandai lunas tunai) dan kapan.
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->string('catatan', 255)->nullable(); // mis. alasan bukti ditolak
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
