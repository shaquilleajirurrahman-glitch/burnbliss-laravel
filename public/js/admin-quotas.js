/*
 * Burn & Bliss — kuota harian: ubah & hapus langsung dari baris tabel (jQuery AJAX + JSON).
 *  PUT    /internal/kuota/{id} -> { success, message, row }
 *  DELETE /internal/kuota/{id} -> { success, message }
 * Gagal validasi (mis. kuota < terpakai) -> 422 { message, errors } -> toast error.
 */
$(function () {
    const $rows = $('#quotaRows');
    if (!$rows.length) return;

    function kirim($form) {
        // Form membawa _method=PUT/DELETE (method spoofing Laravel), jadi dikirim sebagai POST.
        return $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), dataType: 'json' });
    }

    $rows.on('submit', '.js-quota-update', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('[type="submit"]');

        BB.busy($btn, true);
        kirim($form)
            .done(function (res) {
                const $baru = $(res.row);
                $form.closest('tr').replaceWith($baru);
                $baru.addClass('bb-row-flash');
                BB.toast(res.message);
            })
            .fail(function (xhr) {
                BB.toast(BB.errorMessage(xhr), 'error');
                BB.busy($btn, false);
            });
    });

    $rows.on('submit', '.js-quota-delete', function (e) {
        e.preventDefault();
        const $form = $(this);
        if (!confirm($form.data('confirm'))) return;

        kirim($form)
            .done(function (res) {
                $form.closest('tr').fadeOut(200, function () {
                    $(this).remove();
                    if (!$rows.children('tr').length) {
                        $rows.append(
                            $('<tr>').append(
                                $('<td colspan="5" class="text-center text-body-secondary py-5">')
                                    .text('Belum ada kuota khusus. Semua tanggal memakai kuota default.'),
                            ),
                        );
                    }
                });
                BB.toast(res.message);
            })
            .fail(xhr => BB.toast(BB.errorMessage(xhr), 'error'));
    });
});
