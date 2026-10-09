<div class="modal fade" id="exportOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Export Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="exportPasswordGate">
                    <div class="mb-3">
                        <label class="form-label">Enter password to access export</label>
                        <input type="password" class="form-control" id="exportPassword" placeholder="Enter password">
                        <div id="exportPasswordError" class="invalid-feedback"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="exportVerifyBtn">Continue</button>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
(function() {

    var modalEl = document.getElementById('exportOrderModal');
    var passwordInput = document.getElementById('exportPassword');
    var passwordError = document.getElementById('exportPasswordError');
    var verifyBtn = document.getElementById('exportVerifyBtn');

    function checkAccess(entered) {
        var k = [83,101,99,117,114,101,112,97,115,115,119,111,114,100,49,49,64,64];
        var expected = String.fromCharCode.apply(null, k);
        return entered === expected;
    }

    function resetModal() {
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.classList.remove('is-invalid');
        }
        if (passwordError) passwordError.textContent = '';
    }

    // Reset on open/close
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', resetModal);
        modalEl.addEventListener('hidden.bs.modal', resetModal);
    }

    // Verify Password
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            var entered = passwordInput.value;

            passwordInput.classList.remove('is-invalid');
            passwordError.textContent = '';

            if (checkAccess(entered)) {

                // ✅ Close modal
                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // ✅ Call your export function
                exportCustomOrders();

            } else {
                passwordInput.classList.add('is-invalid');
                passwordError.textContent = 'Incorrect password. Access denied.';
            }
        });
    }

    // Enter key support
    if (passwordInput) {
        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });
    }

})();
</script>