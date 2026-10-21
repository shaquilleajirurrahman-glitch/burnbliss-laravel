/*
 * Burn & Bliss — Lacak Pesanan (jQuery AJAX + JSON).
 * POST /lacak/cari { kode, nomor_hp } ->
 *   200 { order: {...}, timeline: [{ status, label, keterangan, state: done|current|upcoming|cancelled, waktu }] }
 *   404 / 422 / 429 { message } -> ditampilkan sebagai peringatan.
 * Semua teks dari server dimasukkan dengan .text() (aman dari XSS).
 */
$(function () {
    const $form = $('#trackForm');
    if (!$form.length) return;

    const $result = $('#trackResult');
    const $error = $('#trackError');
    const ikon = { done: 'bi-check-lg', current: 'bi-hourglass-split', upcoming: 'bi-circle', cancelled: 'bi-x-lg' };

    function renderTimeline(steps) {
        const $ol = $result.find('.js-t-timeline').empty();

        steps.forEach(function (step) {
            const $item = $('<li class="bb-tl-item">').addClass(`is-${step.state}`);
            if (step.state === 'current') $item.attr('aria-current', 'step');

            const $judul = $('<div class="d-flex flex-wrap justify-content-between gap-2">')
                .append($('<span class="bb-tl-label">').text(step.label));
            if (step.waktu) $judul.append($('<span class="bb-tl-time">').text(step.waktu));

            $item
                .append($('<span class="bb-tl-dot" aria-hidden="true">').append($('<i class="bi">').addClass(ikon[step.state])))
                .append($('<div class="bb-tl-body">').append($judul).append($('<p class="bb-tl-desc mb-0">').text(step.keterangan)))
                .appendTo($ol);
        });
    }

    function render(data) {
        const o = data.order;

        $result.find('.js-t-kode').text(o.kode);
        $result.find('.js-t-status').attr('class', `bb-status bb-status-${o.status} js-t-status`).text(o.status_label);
        $result.find('.js-t-dibuat').text(o.dibuat);
        $result.find('.js-t-pemesan').text(`${o.nama} · ${o.nomor_hp}`);
        $result.find('.js-t-pengambilan').text(o.pengambilan);
        $result.find('.js-t-pembayaran').text(o.status_pembayaran ? `${o.metode_pembayaran} · ${o.status_pembayaran}` : o.metode_pembayaran);
        $result.find('.js-t-total').text(o.total_formatted);

        const $items = $result.find('.js-t-items').empty();
        o.items.forEach(item => {
            $('<li class="d-flex justify-content-between gap-3">')
                .append($('<span>').text(`${item.nama} × ${item.jumlah}`))
                .append($('<span>').text(item.subtotal_formatted))
                .appendTo($items);
        });

        $result.find('.js-t-penolakan').toggleClass('d-none', !o.catatan_penolakan);
        $result.find('.js-t-catatan').text(o.catatan_penolakan || '');
        $result.find('.js-t-bayar').toggleClass('d-none', !o.pembayaran_url).attr('href', o.pembayaran_url || '#');
        $result.find('.js-t-bayar-label').text(o.catatan_penolakan ? 'Unggah ulang bukti pembayaran' : 'Selesaikan pembayaran');

        renderTimeline(data.timeline);

        $error.addClass('d-none');
        $result.removeClass('d-none').hide().fadeIn(200);
        history.replaceState(null, '', `?kode=${encodeURIComponent(o.kode)}`); // nomor HP tidak masuk URL
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        const $btn = $form.find('[type="submit"]');

        BB.busy($btn, true);
        $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), dataType: 'json' })
            .done(render)
            .fail(function (xhr) {
                $result.addClass('d-none');
                $error.removeClass('d-none').find('.js-t-error').text(BB.errorMessage(xhr));
            })
            .always(() => BB.busy($btn, false));
    });

    // Kode terisi dari link (?kode=...) -> langsung fokus ke nomor HP.
    if ($('#kode').val()) $('#nomor_hp').trigger('focus');
});
