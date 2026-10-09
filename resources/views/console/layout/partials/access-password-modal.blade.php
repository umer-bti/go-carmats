<div class="modal fade" id="accessPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="accessPasswordInput">Enter password to continue</label>
                    <input type="password" class="form-control" id="accessPasswordInput" placeholder="Enter password" autocomplete="off">
                    <div id="accessPasswordError" class="invalid-feedback"></div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="accessPasswordVerifyBtn">Continue</button>
                </div>
            </div>
        </div>
    </div>
</div>
