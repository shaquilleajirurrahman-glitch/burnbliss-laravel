/*
 * Burn & Bliss — keranjang tamu (jQuery AJAX + JSON).
 *
 * Semua form keranjang tetap berfungsi tanpa JavaScript (POST biasa). Script ini mencegat
 * submit, mengirim AJAX ke endpoint yang sama, lalu memperbarui halaman dari response JSON:
 *   { success, message, cart_count, total, total_formatted, item: { variant_id, jumlah, stok, subtotal_formatted, ... } }
 * Gagal validasi/stok -> HTTP 422 { message, errors } -> ditampilkan sebagai toast error.
 */
$(function () {
    function send($form, data) {
        return $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: data,
            dataType: 'json', // juga mengirim header Accept: application/json
        });
    }

    // ── Katalog: Tambah ke Keranjang ─────────────────────────────
    $(document).on('submit', 'form.js-cart-add', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('[type="submit"]');

        BB.busy($btn, true);
        send($form, $form.serialize())
            .done(function (res) {
                BB.toast(res.message);
                BB.setCartCount(res.cart_count);
                if (res.item) {
                    $form.find('.js-in-cart').removeClass('d-none')
                        .find('.js-in-cart-qty').text(res.item.jumlah);
                }
            })
            .fail(xhr => BB.toast(BB.errorMessage(xhr), 'error'))
            .always(() => BB.busy($btn, false));
    });

    // ── Halaman keranjang ────────────────────────────────────────
    function renderSummary(res) {
        $('.js-cart-total').text(res.total_formatted);
        $('.js-cart-items').text(res.cart_count);
        BB.setCartCount(res.cart_count);

        if (res.cart_count === 0) {
            $('.js-cart-filled').addClass('d-none');
            $('.js-cart-empty').removeClass('d-none');
        }
    }

    function renderLine($line, item) {
        $line.find('.js-qty').text(item.jumlah);
        $line.find('.js-subtotal').text(item.subtotal_formatted);
        // Tombol +/- membawa jumlah tujuan sebagai value-nya (agar tetap jalan tanpa JS).
        $line.find('.js-minus').val(item.jumlah - 1).prop('disabled', item.jumlah <= 1);
        $line.find('.js-plus').val(item.jumlah + 1).prop('disabled', item.jumlah >= item.stok);
    }

    // Tombol + / −
    $(document).on('submit', 'form.js-cart-update', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $line = $form.closest('.js-cart-line');
        const submitter = e.originalEvent && e.originalEvent.submitter;
        if (!submitter) return;

        const $buttons = $line.find('button');
        $buttons.prop('disabled', true);
        $line.addClass('bb-line-busy');

        send($form, $form.serialize() + '&jumlah=' + encodeURIComponent(submitter.value))
            .done(function (res) {
                renderLine($line, res.item);
                renderSummary(res);
            })
            .fail(function (xhr) {
                BB.toast(BB.errorMessage(xhr), 'error');
                // Kembalikan status tombol sesuai jumlah yang tampil.
                const jumlah = parseInt($line.find('.js-qty').text(), 10);
                $buttons.prop('disabled', false);
                $line.find('.js-minus').prop('disabled', jumlah <= 1);
            })
            .always(function () {
                $line.removeClass('bb-line-busy');
                $line.find('.js-cart-remove button').prop('disabled', false);
            });
    });

    // Tombol hapus
    $(document).on('submit', 'form.js-cart-remove', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $line = $form.closest('.js-cart-line');

        $line.find('button').prop('disabled', true);
        $line.addClass('bb-line-busy');

        send($form, $form.serialize())
            .done(function (res) {
                BB.toast(res.message);
                $line.slideUp(200, () => $line.remove());
                renderSummary(res);
            })
            .fail(function (xhr) {
                BB.toast(BB.errorMessage(xhr), 'error');
                $line.removeClass('bb-line-busy').find('button').prop('disabled', false);
            });
    });
});
