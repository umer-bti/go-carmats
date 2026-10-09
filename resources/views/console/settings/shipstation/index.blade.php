@extends('console.layout.app')

@section('title', 'ShipStation Accounts')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('console.settings.index') }}">Settings</a></li>
                <li class="breadcrumb-item active">ShipStation Accounts</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">ShipStation Accounts</h5>
                    <p class="text-muted small mb-0 mt-1">Store API credentials for multiple ShipStation accounts.</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shipstationFormModal" data-mode="create">
                    <i class="icon-base ti tabler-plus me-1"></i> Add account
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>API Key</th>
                                <th>API Secret</th>
                                <th>Status</th>
                                <th style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $acc)
                                <tr>
                                    <td>{{ $acc->name ?: '—' }}</td>
                                    <td>
                                        @if($acc->client_id)
                                            <code>{{ $acc->client_id }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="d-inline-flex align-items-center gap-1">
                                            @if($acc->client_secret)
                                                <code class="shipstation-value-text" data-masked="••••••••" data-value="{{ $acc->client_secret }}">••••••••</code>
                                                <button type="button" class="btn btn-link btn-sm p-0 shipstation-toggle-secret text-body" title="Show API Secret" aria-label="Toggle visibility">
                                                    <i class="icon-base ti tabler-eye icon-18px"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        @if($acc->is_active)
                                            <span class="badge bg-primary">Active</span>
                                        @else
                                            <span class="badge bg-label-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-btn" data-bs-toggle="modal" data-bs-target="#shipstationFormModal" data-mode="edit"
                                            data-id="{{ $acc->id }}"
                                            data-name="{{ $acc->name ?? '' }}"
                                            data-client-id="{{ $acc->client_id ?? '' }}"
                                            data-is-active="{{ $acc->is_active ? '1' : '0' }}">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="{{ $acc->id }}" data-display="{{ $acc->name ?: ($acc->client_id ?: 'ShipStation account') }}">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No ShipStation account. Add one to store credentials.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shipstationFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shipstationModalTitle">Add ShipStation account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="shipstationForm">
                        <input type="hidden" id="shipstationId" name="id">
                        <div class="mb-3">
                            <label for="shipstationName" class="form-label">Account name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="shipstationName" name="name" placeholder="e.g. Main store" required>
                        </div>
                        <div class="mb-3">
                            <label for="shipstationClientId" class="form-label">API Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="shipstationClientId" name="client_id" placeholder="ShipStation API Key">
                        </div>
                        <div class="mb-3">
                            <label for="shipstationClientSecret" class="form-label">API Secret <span class="text-danger create-required">*</span></label>
                            <input type="text" class="form-control" id="shipstationClientSecret" name="client_secret" placeholder="ShipStation API Secret">
                            <div class="form-text edit-only-hint d-none">Leave blank when editing to keep the current secret.</div>
                        </div>
                        <div class="mb-0">
                            <label for="shipstationStatus" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="shipstationStatus" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="shipstationFormSubmit">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    const modal = document.getElementById('shipstationFormModal');
    const form = document.getElementById('shipstationForm');
    const submitBtn = document.getElementById('shipstationFormSubmit');
    const baseUrl = '{{ url("console/settings/shipstation") }}';
    const csrf = '{{ csrf_token() }}';
    const editOnlyHint = document.querySelector('.edit-only-hint');
    const createRequiredMarkers = document.querySelectorAll('.create-required');
    const hasExistingAccounts = {{ $accounts->isNotEmpty() ? 'true' : 'false' }};

    modal.addEventListener('show.bs.modal', function(e) {
        const trigger = e.relatedTarget;
        const mode = trigger && trigger.dataset.mode;
        if (mode === 'create') {
            document.getElementById('shipstationModalTitle').textContent = 'Add ShipStation account';
            document.getElementById('shipstationId').value = '';
            form.reset();
            document.getElementById('shipstationStatus').value = hasExistingAccounts ? '0' : '1';
            if (editOnlyHint) editOnlyHint.classList.add('d-none');
            createRequiredMarkers.forEach(function(el) { el.classList.remove('d-none'); });
        } else if (mode === 'edit' && trigger) {
            document.getElementById('shipstationModalTitle').textContent = 'Edit ShipStation account';
            document.getElementById('shipstationId').value = trigger.dataset.id || '';
            document.getElementById('shipstationName').value = trigger.dataset.name || '';
            document.getElementById('shipstationClientId').value = trigger.dataset.clientId || '';
            document.getElementById('shipstationClientSecret').value = '';
            document.getElementById('shipstationStatus').value = trigger.dataset.isActive === '1' ? '1' : '0';
            if (editOnlyHint) editOnlyHint.classList.remove('d-none');
            createRequiredMarkers.forEach(function(el) { el.classList.add('d-none'); });
        }
    });

    submitBtn.addEventListener('click', function() {
        const id = document.getElementById('shipstationId').value;
        const payload = {
            name: document.getElementById('shipstationName').value.trim() || null,
            client_id: document.getElementById('shipstationClientId').value.trim() || null,
            client_secret: document.getElementById('shipstationClientSecret').value.trim() || null,
            is_active: document.getElementById('shipstationStatus').value === '1',
        };
        const isEdit = !!id;
        const url = isEdit ? baseUrl + '/' + id : baseUrl;
        const method = isEdit ? 'PUT' : 'POST';

        if (!payload.name) {
            toastr.error('Account name is required.');
            return;
        }

        if (!isEdit && (!payload.client_id || !payload.client_secret)) {
            toastr.error('Please enter API Key and API Secret.');
            return;
        }

        if (isEdit && !payload.client_id) {
            toastr.error('API Key is required.');
            return;
        }

        submitBtn.disabled = true;
        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                bootstrap.Modal.getInstance(modal).hide();
                window.location.reload();
            } else {
                toastr.error(data.message || 'Failed');
            }
        })
        .catch(function() { toastr.error('Request failed'); })
        .finally(function() { submitBtn.disabled = false; });
    });

    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const display = this.dataset.display || 'this account';

            Swal.fire({
                title: 'Are you sure?',
                text: 'Delete ShipStation account "' + display + '"?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    fetch(baseUrl + '/' + id, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    })
                    .then(r => r.json())
                    .then(function(data) {
                        if (data.success) {
                            Swal.fire('Deleted!', data.message, 'success').then(function() {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error!', data.message || 'Failed to delete.', 'error');
                        }
                    })
                    .catch(function() {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    });
                }
            });
        });
    });

    document.querySelectorAll('.shipstation-toggle-secret').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cell = this.closest('td');
            var code = cell.querySelector('.shipstation-value-text');
            var icon = this.querySelector('i');
            if (!code || !icon) return;
            var masked = code.getAttribute('data-masked') || '••••••••';
            var value = code.getAttribute('data-value') || '—';
            if (code.textContent === masked) {
                code.textContent = value;
                icon.classList.remove('tabler-eye');
                icon.classList.add('tabler-eye-off');
            } else {
                code.textContent = masked;
                icon.classList.remove('tabler-eye-off');
                icon.classList.add('tabler-eye');
            }
        });
    });
})();
</script>
@endpush
