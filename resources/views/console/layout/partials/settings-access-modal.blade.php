<div class="modal fade" id="settingsAccessModal" tabindex="-1" aria-labelledby="settingsAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsAccessModalLabel">Access Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="settingsAccessModalHint">Enter password to view Settings.</p>
                <div class="mb-3">
                    <label for="settingsModalPassword" class="form-label">Password</label>
                    <input type="password" class="form-control" id="settingsModalPassword" placeholder="Enter password" autocomplete="off">
                    <div id="settingsModalPasswordError" class="invalid-feedback"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="settingsModalVerifyBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modalEl = document.getElementById('settingsAccessModal');
    var modalTitleEl = document.getElementById('settingsAccessModalLabel');
    var modalHintEl = document.getElementById('settingsAccessModalHint');
    var passwordInput = document.getElementById('settingsModalPassword');
    var passwordError = document.getElementById('settingsModalPasswordError');
    var verifyBtn = document.getElementById('settingsModalVerifyBtn');
    var settingsUrl = '{{ route("console.settings.index") }}';
    var settingsVerifyUrl = '{{ route("console.settings.verify") }}';
    var dashboardUrl = '{{ route("console.dashboard") }}';
    var dashboardVerifyUrl = '{{ route("console.dashboard.verify") }}';
    var stitchersUrl = '{{ route("console.stitchers.index") }}';
    var stitchersVerifyUrl = '{{ route("console.stitchers.verify") }}';
    var defaultTitle = 'Access Settings';
    var defaultHint = 'Enter password to view Settings.';

    function resetModal() {
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.classList.remove('is-invalid');
        }
        if (passwordError) passwordError.textContent = '';
        if (verifyBtn) verifyBtn.disabled = false;
    }

    function applyModalCopyFromOptions() {
        var copy = window.__settingsAccessModalCustomCopy;
        if (modalTitleEl) {
            modalTitleEl.textContent = (copy && copy.title) ? copy.title : defaultTitle;
        }
        if (modalHintEl) {
            modalHintEl.textContent = (copy && copy.hint) ? copy.hint : defaultHint;
        }
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
            resetModal();
            applyModalCopyFromOptions();
        });
        modalEl.addEventListener('hidden.bs.modal', function() {
            if (modalTitleEl) modalTitleEl.textContent = defaultTitle;
            if (modalHintEl) modalHintEl.textContent = defaultHint;
            window.__settingsAccessModalCustomCopy = null;
            window.__pendingPasswordGateCallback = null;
            window.__passwordGateVerifyUrl = null;
            window.__passwordGateRedirectUrl = null;
        });
    }

    document.querySelectorAll('.settings-menu-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var path = window.location.pathname || '';
            if (path.indexOf('/console/settings') === 0 && path !== '/console/settings/gate') {
                return;
            }
            e.preventDefault();
            window.__passwordGateVerifyUrl = null;
            window.__passwordGateRedirectUrl = null;
            if (modalEl && typeof bootstrap !== 'undefined') {
                var m = new bootstrap.Modal(modalEl);
                m.show();
            }
        });
    });

    document.querySelectorAll('.dashboard-menu-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var path = window.location.pathname || '';
            if (path.indexOf('/console/dashboard') === 0) {
                return;
            }
            e.preventDefault();
            window.__passwordGateVerifyUrl = dashboardVerifyUrl;
            window.__passwordGateRedirectUrl = dashboardUrl;
            window.__settingsAccessModalCustomCopy = {
                title: 'Access Dashboard',
                hint: 'Enter password to view Dashboard.',
            };
            if (modalEl && typeof bootstrap !== 'undefined') {
                var m = new bootstrap.Modal(modalEl);
                m.show();
            }
        });
    });

    document.querySelectorAll('.stitchers-menu-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var path = window.location.pathname || '';
            if (path.indexOf('/console/stitchers') === 0 && path !== '/console/stitchers/gate') {
                return;
            }
            e.preventDefault();
            window.__passwordGateVerifyUrl = stitchersVerifyUrl;
            window.__passwordGateRedirectUrl = stitchersUrl;
            window.__settingsAccessModalCustomCopy = {
                title: 'Access Stitchers',
                hint: 'Enter password to view Stitchers.',
            };
            if (modalEl && typeof bootstrap !== 'undefined') {
                var m = new bootstrap.Modal(modalEl);
                m.show();
            }
        });
    });

    function doVerify() {
        var pwd = passwordInput.value;
        passwordInput.classList.remove('is-invalid');
        passwordError.textContent = '';
        verifyBtn.disabled = true;
        var verifyUrl = window.__passwordGateVerifyUrl || settingsVerifyUrl;
        fetch(verifyUrl, {
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
                var pwd = passwordInput.value;
                var pending = window.__pendingPasswordGateCallback;
                if (typeof pending === 'function') {
                    window.__pendingPasswordGateCallback = null;
                    window.__settingsAccessModalCustomCopy = null;
                    window.__passwordGateVerifyUrl = null;
                    window.__passwordGateRedirectUrl = null;
                    if (typeof bootstrap !== 'undefined' && modalEl) {
                        var m = bootstrap.Modal.getInstance(modalEl);
                        if (m) m.hide();
                    }
                    pending(pwd);
                    return;
                }
                if (typeof bootstrap !== 'undefined' && modalEl) {
                    var m2 = bootstrap.Modal.getInstance(modalEl);
                    if (m2) m2.hide();
                }
                window.__settingsAccessModalCustomCopy = null;
                var redirectOverride = window.__passwordGateRedirectUrl;
                window.__passwordGateVerifyUrl = null;
                window.__passwordGateRedirectUrl = null;
                window.location.href = redirectOverride || settingsUrl;
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
