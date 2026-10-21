/* Burn & Bliss — interaksi halaman checkout & pembayaran (jQuery). */
$(function () {
    // Alamat & catatan ongkir hanya tampil jika pesanan diantar.
    function syncMetodePengiriman() {
        const diantar = $('input[name="metode_pengiriman"]:checked').val() === 'diantar';
        $('.js-alamat').toggleClass('d-none', !diantar).find('textarea').prop('required', diantar);
        $('.js-ongkir-note').toggleClass('d-none', !diantar);
    }
    if ($('input[name="metode_pengiriman"]').length) {
        $(document).on('change', 'input[name="metode_pengiriman"]', syncMetodePengiriman);
        syncMetodePengiriman();
    }

    // Keterangan langkah berikutnya mengikuti metode pembayaran (tunai tidak perlu unggah bukti).
    $(document).on('change', 'input[name="metode_pembayaran"]', function () {
        const tunai = $(this).val() === 'cash';
        $('.js-next-upload').toggleClass('d-none', tunai);
        $('.js-next-cash').toggleClass('d-none', !tunai);
    });

    // Tombol salin (kode pesanan, nomor rekening).
    $(document).on('click', '.js-copy', function () {
        const text = String($(this).data('copy'));

        if (!navigator.clipboard) {
            BB.toast('Browser tidak mendukung salin otomatis. Salin secara manual.', 'error');
            return;
        }
        navigator.clipboard.writeText(text).then(
            () => BB.toast(`Disalin: ${text}`),
            () => BB.toast('Gagal menyalin. Salin secara manual.', 'error'),
        );
    });

    // Cek tipe & ukuran bukti sebelum upload, lalu tampilkan pratinjau.
    // (Server tetap memvalidasi ulang; ini hanya agar pelanggan tidak menunggu upload yang pasti ditolak.)
    $(document).on('change', '.js-bukti-input', function () {
        const file = this.files[0];
        const $preview = $('.js-bukti-preview');
        const maksByte = Number($(this).data('max-kb')) * 1024;

        $preview.addClass('d-none').attr('src', '');
        if (!file) return;

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            BB.toast('Bukti pembayaran harus berupa gambar JPG atau PNG.', 'error');
            this.value = '';
            return;
        }
        if (file.size > maksByte) {
            BB.toast('Ukuran bukti pembayaran maksimal 2 MB.', 'error');
            this.value = '';
            return;
        }

        $preview.attr('src', URL.createObjectURL(file)).removeClass('d-none');
    });
});
