<div class="modal fade" id="addorderFileModal" tabindex="-1" aria-labelledby="addorderFileModalLabel" aria-hidden="true"
    data-import-action="{{ route('console.orders.import') }}"
    data-import-csrf="{{ csrf_token() }}"
    data-format2-template-url="{{ route('console.orders.format2Template') }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addorderFileModalLabel">Import Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Only the password gate is in the DOM; import form is injected by JS after correct password --}}
                <div id="importOrderPasswordGate" class="import-order-gate">
                    <div class="mb-3">
                        <label for="importOrderPassword" class="form-label">Enter password to access import</label>
                        <input type="password" class="form-control" id="importOrderPassword" placeholder="Enter password" autocomplete="off">
                        <div id="importOrderPasswordError" class="invalid-feedback"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="importOrderVerifyBtn">Continue</button>
                    </div>
                </div>
                <div id="importOrderFieldsContainer" class="import-order-fields-container"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modalEl = document.getElementById('addorderFileModal');
    var gateEl = document.getElementById('importOrderPasswordGate');
    var containerEl = document.getElementById('importOrderFieldsContainer');
    var passwordInput = document.getElementById('importOrderPassword');
    var passwordError = document.getElementById('importOrderPasswordError');
    var verifyBtn = document.getElementById('importOrderVerifyBtn');

    function checkAccess(entered) {
        var k = [83,101,99,117,114,101,112,97,115,115,119,111,114,100,49,49,64,64];
        var expected = String.fromCharCode.apply(null, k);
        return entered === expected;
    }

    function getFormTemplate(action, csrf, format2TemplateUrl) {
        var a = action || '';
        var c = csrf || '';
        var templateUrl = (format2TemplateUrl || '').replace(/"/g, '&quot;');
        return '<form id="addorderFileForm" method="POST" action="' + (a.replace(/"/g, '&quot;')) + '" enctype="multipart/form-data">' +
            '<input type="hidden" name="_token" value="' + (c.replace(/"/g, '&quot;')) + '">' +
            '<div class="mb-3"><label for="orderFile" class="form-label">Upload Excel File</label>' +
            '<input type="file" class="form-control" id="orderFile" name="orderFile" required accept=".xlsx, .xls, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">' +
            '<div class="form-text"><strong>Note:</strong> Select the file format below. By default, Format 1 (single sheet, auto-detect) is used.</div></div>' +
            '<div class="mb-3"><label for="importFormat" class="form-label">Select Import Format</label>' +
            '<select class="form-select" id="importFormat" name="import_format">' +
            '<option value="format1" selected>Format 1 - Single sheet with auto detection (current)</option>' +
            '<option value="format2">Format 2 - Two sheets (Carpet &amp; Rubber) with separate explicit columns</option>' +
            '</select>' +
            (templateUrl ? (' <span id="format2TemplateLinkWrap" style="display:none;"><a href="' + templateUrl + '" class="btn btn-outline-primary btn-sm mt-2" target="_blank" rel="noopener">Download Format 2 template</a></span>') : '') +
            '</div>' +
            '<div class="modal-footer px-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>' +
            '<button type="submit" class="btn btn-primary">Import Orders</button></div></form>';
    }

    function resetImportOrderModal() {
        gateEl.style.display = '';
        if (containerEl) {
            containerEl.innerHTML = '';
            containerEl.style.display = 'none';
        }
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.classList.remove('is-invalid');
        }
        if (passwordError) passwordError.textContent = '';
    }

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', resetImportOrderModal);
        modalEl.addEventListener('hidden.bs.modal', resetImportOrderModal);
    }

    if (verifyBtn && passwordInput && containerEl) {
        verifyBtn.addEventListener('click', function() {
            var entered = passwordInput.value;
            passwordInput.classList.remove('is-invalid');
            passwordError.textContent = '';
            if (checkAccess(entered)) {
                gateEl.style.display = 'none';
                var action = modalEl.getAttribute('data-import-action') || '';
                var csrf = modalEl.getAttribute('data-import-csrf') || '';
                var format2TemplateUrl = modalEl.getAttribute('data-format2-template-url') || '';
                containerEl.innerHTML = getFormTemplate(action, csrf, format2TemplateUrl);
                containerEl.style.display = 'block';
                var formatSelect = containerEl.querySelector('#importFormat');
                var templateLinkWrap = containerEl.querySelector('#format2TemplateLinkWrap');
                if (formatSelect && templateLinkWrap) {
                    function toggleFormat2Link() {
                        templateLinkWrap.style.display = formatSelect.value === 'format2' ? '' : 'none';
                    }
                    formatSelect.addEventListener('change', toggleFormat2Link);
                    toggleFormat2Link();
                }
            } else {
                passwordInput.classList.add('is-invalid');
                passwordError.textContent = 'Incorrect password. Access denied.';
            }
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (verifyBtn) verifyBtn.click();
            }
        });
    }
})();
</script>
