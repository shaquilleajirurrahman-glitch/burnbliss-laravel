/*
 * Burn & Bliss — panel internal "Kelola Pesanan" (jQuery AJAX + JSON).
 *
 *  GET  /internal/pesanan?status=&q=&page=   -> { html, pagination, info, counts }
 *  GET  /internal/pesanan/{kode}             -> detail + pembayaran untuk modal
 *  POST /internal/pesanan/{kode}/verifikasi  -> { success, message, row, counts, order }
 *  POST /internal/pesanan/{kode}/status
 *  POST /internal/pesanan/{kode}/lunas
 * Gagal validasi -> 422, tidak berhak -> 403; keduanya { message } dan ditampilkan sebagai toast.
 */
$(function () {
    const $page = $('#ordersPage');
    if (!$page.length) return;

    const listUrl = $page.data('list-url');
    const state = {
        status: $('.js-filter.active').data('status') || '',
        q: $('#searchOrders').val() || '',
        page: 1,
    };
    let listXhr = null;
    let searchTimer = null;

    // ── Daftar: filter, pencarian, halaman ──────────────────────
    function renderCounts(counts) {
        $('.js-count').each(function () {
            const key = $(this).data('status');
            if (counts[key] !== undefined) $(this).text(counts[key]);
        });
    }

    function loadList(page = 1) {
        state.page = page;
        if (listXhr) listXhr.abort(); // batalkan request lama saat mengetik cepat

        $('#ordersTable').addClass('bb-loading');
        listXhr = $.ajax({ url: listUrl, data: state, dataType: 'json' })
            .done(function (res) {
                $('#ordersBody').html(res.html);
                $('#ordersPagination').html(res.pagination);
                $('#ordersInfo').text(res.info);
                renderCounts(res.counts);

                // Simpan filter di URL agar bisa di-refresh/dibagikan.
                const params = new URLSearchParams();
                if (state.status) params.set('status', state.status);
                if (state.q) params.set('q', state.q);
                if (state.page > 1) params.set('page', state.page);
                history.replaceState(null, '', params.toString() ? `?${params}` : location.pathname);
            })
            .fail(function (xhr) {
                if (xhr.statusText !== 'abort') BB.toast(BB.errorMessage(xhr), 'error');
            })
            .always(() => $('#ordersTable').removeClass('bb-loading'));
    }

    $(document).on('click', '.js-filter', function () {
        $('.js-filter').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        state.status = $(this).data('status') || '';
        loadList(1);
    });

    $('#searchOrders').on('input', function () {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        searchTimer = setTimeout(() => {
            state.q = q;
            loadList(1);
        }, 300);
    });

    $(document).on('click', '#ordersPagination a.page-link', function (e) {
        e.preventDefault();
        const page = Number(new URL(this.href).searchParams.get('page')) || 1;
        loadList(page);
    });

    // ── Aksi per baris (ubah status produksi, tandai lunas) ─────
    function replaceRow(res) {
        const $newRow = $(res.row);
        $(`#order-${res.order.kode}`).replaceWith($newRow);
        $newRow.addClass('bb-row-flash');
        renderCounts(res.counts);
        BB.toast(res.message);
    }

    function postAction(url, payload) {
        return $.ajax({ url: url, method: 'POST', data: payload, dataType: 'json' })
            .done(replaceRow)
            .fail(xhr => BB.toast(BB.errorMessage(xhr), 'error'));
    }

    $(document).on('click', '.js-order-action', function () {
        const $btn = $(this);
        if (!confirm($btn.data('confirm'))) return;

        BB.busy($btn, true);
        postAction($btn.data('url'), $btn.data('payload') || {})
            .fail(() => BB.busy($btn, false)); // jika sukses, tombol ikut terganti baris baru
    });

    // ── Modal detail & verifikasi bukti pembayaran ──────────────
    const $modal = $('#orderModal');
    if (!$modal.length) return; // Staf tidak punya modal (tidak boleh melihat data pembayaran)

    const modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
    let verifyUrl = null;

    function fillModal(data) {
        $modal.find('.js-m-kode').text(data.kode);
        $modal.find('.js-m-nama').text(data.nama);
        $modal.find('.js-m-hp').text(data.nomor_hp);
        $modal.find('.js-m-pengambilan').text(data.pengambilan);
        $modal.find('.js-m-alamat').text(data.alamat || '—');
        $modal.find('.js-m-status').text(data.status_label);
        $modal.find('.js-m-metode').text(data.metode_pembayaran);
        $modal.find('.js-m-total').text(data.total_formatted);

        const p = data.payment;
        $modal.find('.js-m-paystatus').text(p ? `${p.status_label} · ${p.jumlah_formatted}` : 'Belum ada');
        $modal.find('.js-m-dibuat').text(p ? p.dibuat : '—');
        $modal.find('.js-m-verifikator').text(p && p.verifikator ? p.verifikator : '—');
        $modal.find('.js-m-catatan').text(p && p.catatan ? p.catatan : '—');

        const $items = $modal.find('.js-m-items').empty();
        data.items.forEach(item => {
            $('<li class="d-flex justify-content-between gap-3">')
                .append($('<span>').text(`${item.nama} × ${item.jumlah}`))
                .append($('<span>').text(item.subtotal_formatted))
                .appendTo($items);
        });

        const buktiUrl = p && p.bukti_url;
        $modal.find('.js-m-proof').attr('src', buktiUrl || '');
        $modal.find('.js-m-proof-link').attr('href', buktiUrl || '#').toggleClass('d-none', !buktiUrl);
        $modal.find('.js-m-no-proof').toggleClass('d-none', !!buktiUrl);

        verifyUrl = data.verify_url;
        $modal.find('.js-m-verify, .js-m-actions').toggleClass('d-none', !data.bisa_verifikasi);
        $('#catatanVerifikasi').val('');

        $modal.find('.js-m-loading').addClass('d-none');
        $modal.find('.js-m-content').removeClass('d-none');
    }

    $(document).on('click', '.js-open-detail', function () {
        $modal.find('.js-m-loading').removeClass('d-none');
        $modal.find('.js-m-content, .js-m-actions').addClass('d-none');
        $modal.find('.js-m-kode').text('');
        modal.show();

        $.ajax({ url: $(this).data('url'), dataType: 'json' })
            .done(fillModal)
            .fail(function (xhr) {
                modal.hide();
                BB.toast(BB.errorMessage(xhr), 'error');
            });
    });

    $modal.on('click', '.js-verify', function () {
        const $btn = $(this);
        const keputusan = $btn.data('keputusan');
        const kode = $modal.find('.js-m-kode').text();
        const tanya = keputusan === 'terima'
            ? `Terima pembayaran ${kode}? Pesanan akan masuk antrean produksi.`
            : `Tolak bukti pembayaran ${kode}? Pelanggan perlu mengunggah ulang.`;
        if (!confirm(tanya)) return;

        $modal.find('.js-verify').prop('disabled', true);
        BB.busy($btn, true);
        postAction(verifyUrl, { keputusan: keputusan, catatan: $('#catatanVerifikasi').val() })
            .done(() => modal.hide())
            .always(function () {
                BB.busy($btn, false);
                $modal.find('.js-verify').prop('disabled', false);
            });
    });

    // Hentikan pemuatan gambar bukti saat modal ditutup.
    $modal.on('hidden.bs.modal', () => $modal.find('.js-m-proof').attr('src', ''));
});
