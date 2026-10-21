/* Burn & Bliss — interaksi global (jQuery + Bootstrap 5) */

// Helper global yang dipakai script halaman (mis. cart.js).
window.BB = {
    /**
     * Tampilkan toast Bootstrap. Markup di-clone dari <template id="bbToastTemplate">
     * (resources/views/partials/_toast.blade.php) dan pesan dimasukkan sebagai teks (aman dari XSS).
     */
    toast(message, type = 'success') {
        const isError = type === 'error';
        const $toast = $($('#bbToastTemplate').html().trim());

        $toast.toggleClass('bb-toast-error', isError)
            .attr({ role: isError ? 'alert' : 'status', 'aria-live': isError ? 'assertive' : 'polite' });
        $toast.find('.bi').first().toggleClass('bi-check2-circle', !isError).toggleClass('bi-exclamation-circle', isError);
        $toast.find('.js-toast-message').text(message);

        $('#bbToasts').append($toast);
        $toast.on('hidden.bs.toast', () => $toast.remove());
        bootstrap.Toast.getOrCreateInstance($toast[0], { delay: 3500 }).show();
    },

    /** Perbarui badge keranjang di navbar (dengan animasi kecil). */
    setCartCount(count) {
        const $badge = $('.js-cart-count');
        $badge.text(count).toggleClass('d-none', count === 0).removeClass('bb-bump');
        void $badge[0]?.offsetWidth; // restart animasi CSS
        $badge.addClass('bb-bump');
        $('[data-cart-link]').attr('aria-label', `Keranjang, ${count} item`);
    },

    /** Ambil pesan error yang bisa dibaca dari response AJAX Laravel. */
    errorMessage(xhr) {
        if (xhr.status === 419) return 'Sesi Anda kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        if (xhr.status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';

        const res = xhr.responseJSON;
        if (res?.errors) return Object.values(res.errors)[0][0];
        if (res?.message) return res.message;
        return 'Terjadi kesalahan. Periksa koneksi lalu coba lagi.';
    },

    /** Status loading pada tombol: nonaktif + spinner. */
    busy($btn, on) {
        $btn.prop('disabled', on).attr('aria-busy', on);
        if (on) {
            $btn.prepend('<span class="spinner-border spinner-border-sm me-2 js-spinner" aria-hidden="true"></span>');
        } else {
            $btn.find('.js-spinner').remove();
        }
    },
};

// Semua request AJAX jQuery otomatis membawa token CSRF Laravel.
$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
});

$(function () {
    // Tampilkan toast flash message dari server (session('status') / error validasi).
    $('#bbToasts .toast').each(function () {
        bootstrap.Toast.getOrCreateInstance(this, { delay: 4000 }).show();
    });

    // Tombol lihat/sembunyikan password.
    $(document).on('click', '.bb-toggle-password', function () {
        const $input = $($(this).data('target'));
        const show = $input.attr('type') === 'password';

        $input.attr('type', show ? 'text' : 'password');
        $(this).attr('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password')
            .find('.bi').toggleClass('bi-eye bi-eye-slash');
    });

    // Konfirmasi sebelum submit form berisiko (mis. hapus produk): <form class="js-confirm" data-confirm="...">.
    $(document).on('submit', 'form.js-confirm', function (e) {
        if (!confirm($(this).data('confirm'))) e.preventDefault();
    });

    // Cegah submit ganda: nonaktifkan tombol dan tampilkan spinner.
    $('form.bb-form').on('submit', function () {
        const $btn = $(this).find('[type="submit"]');
        $btn.prop('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
    });
});
