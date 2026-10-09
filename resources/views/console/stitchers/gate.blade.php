@extends('console.layout.app')

@section('title', 'Stitchers – Enter Password')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Access Stitchers</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Enter password to view Stitchers.</p>
                        <div class="mb-3">
                            <label for="stitchersGatePassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="stitchersGatePassword" placeholder="Enter password" autocomplete="off">
                            <div id="stitchersGatePasswordError" class="invalid-feedback"></div>
                        </div>
                        <input type="hidden" id="stitchersGateReturnUrl" value="{{ $returnUrl }}">
                        <div class="d-flex gap-2">
                            <a href="{{ route('console.dashboard') }}" class="btn btn-secondary">Cancel</a>
                            <button type="button" class="btn btn-primary" id="stitchersGateVerifyBtn">Continue</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
(function() {
    var passwordInput = document.getElementById('stitchersGatePassword');
    var passwordError = document.getElementById('stitchersGatePasswordError');
    var verifyBtn = document.getElementById('stitchersGateVerifyBtn');
    var returnUrl = document.getElementById('stitchersGateReturnUrl').value;

    function doVerify() {
        var pwd = passwordInput.value;
        passwordInput.classList.remove('is-invalid');
        passwordError.textContent = '';
        verifyBtn.disabled = true;
        fetch('{{ route("console.stitchers.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ password: pwd }),
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(result) {
            if (result.ok && result.data.success) {
                window.location.href = returnUrl;
            } else {
                passwordInput.classList.add('is-invalid');
                passwordError.textContent = result.data.message || 'Incorrect password. Access denied.';
                verifyBtn.disabled = false;
            }
        })
        .catch(function() {
            passwordError.textContent = 'Request failed.';
            passwordInput.classList.add('is-invalid');
            verifyBtn.disabled = false;
        });
    }

    if (verifyBtn) verifyBtn.addEventListener('click', doVerify);
    if (passwordInput) {
        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doVerify(); }
        });
    }
})();
</script>
@endpush
@endsection
