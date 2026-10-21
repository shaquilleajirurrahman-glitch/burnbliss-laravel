{{--
    Satu toast Bootstrap. Dipakai untuk flash message dari server dan sebagai <template>
    yang di-clone oleh BB.toast() di public/js/burnbliss.js (markup tetap satu sumber).
    Variabel: $type ('success'|'error'), $message (string).
--}}
<div @class(['toast bb-toast align-items-center border-0', 'bb-toast-error' => $type === 'error'])
     role="{{ $type === 'error' ? 'alert' : 'status' }}" aria-live="{{ $type === 'error' ? 'assertive' : 'polite' }}" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body">
            <i @class(['bi me-2', 'bi-check2-circle' => $type !== 'error', 'bi-exclamation-circle' => $type === 'error'])></i><span class="js-toast-message">{{ $message }}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
    </div>
</div>
