/*
 * Burn & Bliss — laporan penjualan (jQuery AJAX + JSON).
 * Saat filter berubah, GET /internal/laporan?dari=&sampai=&varian= dengan Accept: application/json
 * -> { html, export_url, ringkasan }. Tabel & link "Ekspor CSV" diperbarui tanpa reload.
 */
$(function () {
    const $form = $('#reportFilter');
    if (!$form.length) return;

    let xhr = null;
    let timer = null;

    function load() {
        const dari = $('#dari').val();
        const sampai = $('#sampai').val();
        if (!dari || !sampai) return; // tunggu sampai kedua tanggal terisi

        if (xhr) xhr.abort();
        $('#reportResult').addClass('bb-loading-block');

        const query = $form.serialize();
        xhr = $.ajax({ url: $form.attr('action'), data: query, dataType: 'json' })
            .done(function (res) {
                $('#reportResult').html(res.html);
                $('.js-export').attr('href', res.export_url).removeClass('disabled').removeAttr('aria-disabled tabindex');
                history.replaceState(null, '', `?${query}`);
            })
            .fail(function (x) {
                if (x.statusText === 'abort') return;

                const pesan = BB.errorMessage(x);
                BB.toast(pesan, 'error');
                // Jangan biarkan tabel lama tampil seolah hasil filter baru; ekspor dinonaktifkan sampai filter valid.
                $('#reportResult').empty().append(
                    $('<div class="alert alert-warning d-flex gap-2" role="alert">')
                        .append('<i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>')
                        .append($('<div>').text(`${pesan} Perbaiki filter untuk menampilkan laporan.`)),
                );
                $('.js-export').addClass('disabled').attr({ 'aria-disabled': 'true', tabindex: '-1' });
            })
            .always(() => $('#reportResult').removeClass('bb-loading-block'));
    }

    // Filter berubah -> muat ulang tabel (sedikit jeda agar tidak memanggil server di setiap ketukan).
    $form.on('change', 'input, select', function () {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        load();
    });
});
