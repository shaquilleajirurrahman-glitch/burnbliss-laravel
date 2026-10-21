{{--
    Modal detail pesanan & bukti pembayaran (khusus Admin/Koordinator).
    Diisi oleh public/js/admin-orders.js dari JSON GET internal.orders.show.
--}}
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="orderModalTitle">
                    Pesanan <span class="bb-kode-sm js-m-kode"></span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="text-center py-5 js-m-loading">
                    <div class="spinner-border text-maroon" role="status"><span class="visually-hidden">Memuat…</span></div>
                </div>

                <div class="row g-4 d-none js-m-content">
                    <div class="col-md-6">
                        <a href="#" target="_blank" rel="noopener" class="d-block js-m-proof-link" title="Buka ukuran penuh">
                            <img src="" alt="Bukti pembayaran" class="bb-proof-img js-m-proof">
                        </a>
                        <div class="bb-proof-empty d-none js-m-no-proof">
                            <i class="bi bi-cash-coin fs-1 text-maroon" aria-hidden="true"></i>
                            <p class="mb-0 mt-2">Tidak ada file bukti untuk pesanan ini.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <dl class="bb-detail-list small mb-3">
                            <dt>Pelanggan</dt><dd class="js-m-nama"></dd>
                            <dt>No. HP</dt><dd class="js-m-hp"></dd>
                            <dt>Ambil/Kirim</dt><dd class="js-m-pengambilan"></dd>
                            <dt>Alamat</dt><dd class="js-m-alamat"></dd>
                            <dt>Status</dt><dd class="js-m-status"></dd>
                            <dt>Pembayaran</dt><dd class="js-m-metode"></dd>
                            <dt>Status bayar</dt><dd class="js-m-paystatus"></dd>
                            <dt>Diunggah</dt><dd class="js-m-dibuat"></dd>
                            <dt>Verifikator</dt><dd class="js-m-verifikator"></dd>
                            <dt>Catatan</dt><dd class="js-m-catatan"></dd>
                        </dl>

                        <ul class="list-unstyled bb-summary-list small mb-2 js-m-items"></ul>
                        <div class="d-flex justify-content-between align-items-baseline border-top pt-2">
                            <span class="fw-semibold">Total tagihan</span>
                            <span class="bb-price fs-5 js-m-total"></span>
                        </div>

                        <div class="mt-3 js-m-verify">
                            <label for="catatanVerifikasi" class="form-label">Catatan (opsional, mis. alasan penolakan)</label>
                            <textarea id="catatanVerifikasi" class="form-control" rows="2" maxlength="255"></textarea>
                            <div class="form-text">Cocokkan nominal dan nama pengirim dengan mutasi rekening/QRIS sebelum menerima.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer d-none js-m-actions">
                <button type="button" class="btn btn-outline-maroon js-verify" data-keputusan="tolak">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Tolak Pembayaran
                </button>
                <button type="button" class="btn btn-maroon js-verify" data-keputusan="terima">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Terima Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>
