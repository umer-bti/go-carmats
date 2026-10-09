@push('styles')
    <style>
        #loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        #loader .spinner-border {
            width: 170px;
            height: 170px;
            border-width: 11px;
            color: #fff;
        }
    </style>
@endpush

<div id="loader" class="d-none">
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

@push('scripts')
    <script>
        const toggleLoader = (show = true) => document.querySelector('#loader')?.classList.toggle('d-none', !show);
    </script>
@endpush
