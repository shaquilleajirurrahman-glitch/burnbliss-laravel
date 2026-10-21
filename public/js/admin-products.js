/* Burn & Bliss — form tambah/ubah produk: baris varian dinamis, harga jual live, pratinjau foto (jQuery). */
$(function () {
    const $rows = $('#variantRows');
    if (!$rows.length) return;

    const rupiah = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, Number(n) || 0));

    // Harga jual tiap varian = harga dasar + tambahan harga.
    function refreshHarga() {
        const dasar = Number($('#harga').val()) || 0;
        $rows.find('.js-variant-row').each(function () {
            const tambahan = Number($(this).find('.js-tambahan').val()) || 0;
            $(this).find('.js-harga-jual').text(rupiah(dasar + tambahan));
        });
    }
    $('#harga').on('input', refreshHarga);
    $rows.on('input', '.js-tambahan', refreshHarga);

    // Tambah varian: clone <template>, ganti __INDEX__ dengan indeks unik berikutnya.
    $('.js-add-variant').on('click', function () {
        if ($rows.find('.js-variant-row').length >= 10) {
            BB.toast('Maksimal 10 varian per produk.', 'error');
            return;
        }
        const index = Number($rows.data('next-index'));
        $rows.data('next-index', index + 1);

        const html = $('#variantTemplate').html().replaceAll('__INDEX__', index);
        const $row = $(html).appendTo($rows);
        refreshHarga();
        $row.find('input[type="text"]').first().trigger('focus');
    });

    // Hapus varian (minimal satu varian tersisa).
    $rows.on('click', '.js-remove-variant', function () {
        if ($rows.find('.js-variant-row').length <= 1) {
            BB.toast('Produk harus punya minimal satu varian.', 'error');
            return;
        }
        $(this).closest('.js-variant-row').slideUp(150, function () { $(this).remove(); });
    });

    // Pratinjau foto produk sebelum diunggah.
    $('.js-image-input').on('change', function () {
        const file = this.files[0];
        const $preview = $($(this).data('preview'));
        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            BB.toast('Foto harus JPG, PNG, atau WEBP dengan ukuran maksimal 2 MB.', 'error');
            this.value = '';
            return;
        }
        $preview.attr('src', URL.createObjectURL(file)).removeClass('d-none');
    });
});
