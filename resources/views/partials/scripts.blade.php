{{--
    Toast + JavaScript bersama. Variabel opsional: $showErrors (bool) menampilkan error
    validasi pertama sebagai toast (dipakai toko; halaman login menampilkan error di form).
--}}
<div id="bbToasts" class="toast-container position-fixed bottom-0 end-0 p-3">
    @if (session('status'))
        @include('partials._toast', ['type' => 'success', 'message' => session('status')])
    @endif
    @if (($showErrors ?? false) && $errors->any())
        @include('partials._toast', ['type' => 'error', 'message' => $errors->first()])
    @endif
</div>
<template id="bbToastTemplate">
    @include('partials._toast', ['type' => 'success', 'message' => ''])
</template>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="{{ asset('js/burnbliss.js') }}?v={{ filemtime(public_path('js/burnbliss.js')) }}"></script>
@stack('scripts')
