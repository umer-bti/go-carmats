@include('console.layout.partials.access-password-modal')

<script>
    (function initAccessPasswordGate() {
        if (window.requestAccessPassword) {
            return;
        }

        const modalEl = document.getElementById('accessPasswordModal');
        const passwordInput = document.getElementById('accessPasswordInput');
        const passwordError = document.getElementById('accessPasswordError');
        const verifyBtn = document.getElementById('accessPasswordVerifyBtn');

        let onVerifiedCallback = null;

        function checkAccess(entered) {
            const k = [83, 101, 99, 117, 114, 101, 112, 97, 115, 115, 119, 111, 114, 100, 49, 49, 64, 64];
            const expected = String.fromCharCode.apply(null, k);

            return entered === expected;
        }

        function resetModal() {
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.classList.remove('is-invalid');
            }
            if (passwordError) {
                passwordError.textContent = '';
            }
        }

        window.requestAccessPassword = function(options) {
            options = options || {};

            if (!modalEl) {
                Swal.fire('Error!', 'Password modal is not available.', 'error');
                return;
            }

            onVerifiedCallback = typeof options.onVerified === 'function' ? options.onVerified : null;

            const titleEl = modalEl.querySelector('.modal-title');
            if (titleEl) {
                titleEl.textContent = options.title || 'Confirm Delete';
            }

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        window.parseProtectedDeleteResponse = async function(response) {
            const data = await response.json();

            if (!response.ok) {
                throw { response, data };
            }

            return data;
        };

        window.showProtectedDeleteError = function(error, fallbackMessage) {
            const message = error?.data?.message || fallbackMessage || 'Something went wrong.';
            Swal.fire('Error!', message, 'error');
        };

        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', resetModal);
            modalEl.addEventListener('hidden.bs.modal', function() {
                resetModal();
                onVerifiedCallback = null;
            });
        }

        if (verifyBtn) {
            verifyBtn.addEventListener('click', function() {
                const entered = passwordInput ? passwordInput.value : '';

                if (passwordInput) {
                    passwordInput.classList.remove('is-invalid');
                }
                if (passwordError) {
                    passwordError.textContent = '';
                }

                if (!checkAccess(entered)) {
                    if (passwordInput) {
                        passwordInput.classList.add('is-invalid');
                    }
                    if (passwordError) {
                        passwordError.textContent = 'Incorrect password. Access denied.';
                    }
                    return;
                }

                const callback = onVerifiedCallback;
                onVerifiedCallback = null;

                bootstrap.Modal.getInstance(modalEl)?.hide();

                if (callback) {
                    callback(entered);
                }
            });
        }

        if (passwordInput && verifyBtn) {
            passwordInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyBtn.click();
                }
            });
        }
    })();
</script>
