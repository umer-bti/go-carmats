@extends('console.layout.app')

@section('title', 'Evri Accounts')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('console.settings.index') }}">Settings</a></li>
                <li class="breadcrumb-item active">Evri Accounts</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="card-title mb-0">Evri Accounts</h5>
                    <p class="text-muted small mb-0 mt-1">You can add multiple accounts; only the <strong>active</strong> one will be used for printing shipping labels.</p>
                </div>
                {{-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#evriFormModal" data-mode="create">
                    <i class="icon-base ti tabler-plus me-1"></i> Add account
                </button> --}}
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Client ID</th>
                                <th>Client Name</th>
                                <th>Child Client ID</th>
                                <th>Child Client Name</th>
                                <th>Status</th>
                                {{-- <th style="width: 180px">Actions</th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $acc)
                                <tr>
                                    <td>
                                        @if($acc->api_key)
                                            <code>{{ $acc->api_key }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <code class="evri-value-text" data-masked="••••••••" data-value="{{ $acc->api_secret ?? '' }}">••••••••</code>
                                            @if($acc->api_secret)
                                                <button type="button" class="btn btn-link btn-sm p-0 evri-toggle-secret text-body" title="Show Password" aria-label="Toggle visibility">
                                                    <i class="icon-base ti tabler-eye icon-18px"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td><code>{{ $acc->client_id ?: '—' }}</code></td>
                                    <td>{{ $acc->client_name ?: '—' }}</td>
                                    <td><code>{{ $acc->child_client_id ?: '—' }}</code></td>
                                    <td>{{ $acc->child_client_name ?: '—' }}</td>
                                    <td>
                                        @if($acc->is_active)
                                            <span class="badge bg-primary">Active</span>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary set-active-btn" data-id="{{ $acc->id }}">
                                                Set active
                                            </button>
                                        @endif
                                    </td>
                                    {{-- <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-btn" data-bs-toggle="modal" data-bs-target="#evriFormModal" data-mode="edit"
                                            data-id="{{ $acc->id }}"
                                            data-api-key="{{ $acc->api_key ?? '' }}"
                                            data-client-id="{{ $acc->client_id }}"
                                            data-client-name="{{ $acc->client_name }}"
                                            data-child-client-id="{{ $acc->child_client_id }}"
                                            data-child-client-name="{{ $acc->child_client_name }}">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="{{ $acc->id }}" data-display="{{ $acc->client_id ?: 'Evri account' }}">
                                            Delete
                                        </button>
                                    </td> --}}
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No Evri account. Add one to print labels.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    <div class="modal fade" id="evriFormModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="evriModalTitle">Add Evri account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="evriForm">
                        <input type="hidden" id="evriId" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="evriApiKey" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="evriApiKey" name="api_key" placeholder="Evri username">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evriApiSecret" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="evriApiSecret" name="api_secret" placeholder="Evri password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evriClientId" class="form-label">Client ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="evriClientId" name="client_id" placeholder="e.g. 178">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evriClientName" class="form-label">Client Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="evriClientName" name="client_name" placeholder="e.g. Erde Ventus Ltd">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evriChildClientId" class="form-label">Child Client ID <span class="text-muted">(optional)</span></label>
                                <input type="text" class="form-control" id="evriChildClientId" name="child_client_id" placeholder="e.g. 017">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evriChildClientName" class="form-label">Child Client Name <span class="text-muted">(optional)</span></label>
                                <input type="text" class="form-control" id="evriChildClientName" name="child_client_name" placeholder="e.g. GCM">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="evriFormSubmit">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    const modal = document.getElementById('evriFormModal');
    const form = document.getElementById('evriForm');
    const submitBtn = document.getElementById('evriFormSubmit');
    const baseUrl = '{{ url("console/settings/evri") }}';
    const csrf = '{{ csrf_token() }}';

    modal.addEventListener('show.bs.modal', function(e) {
        const trigger = e.relatedTarget;
        const mode = trigger && trigger.dataset.mode;
        if (mode === 'create') {
            document.getElementById('evriModalTitle').textContent = 'Add Evri account';
            document.getElementById('evriId').value = '';
            form.reset();
        } else if (mode === 'edit' && trigger) {
            document.getElementById('evriModalTitle').textContent = 'Edit Evri account';
            document.getElementById('evriId').value = trigger.dataset.id || '';
            document.getElementById('evriApiKey').value = trigger.dataset.apiKey || '';
            document.getElementById('evriClientId').value = trigger.dataset.clientId || '';
            document.getElementById('evriClientName').value = trigger.dataset.clientName || '';
            document.getElementById('evriChildClientId').value = trigger.dataset.childClientId || '';
            document.getElementById('evriChildClientName').value = trigger.dataset.childClientName || '';
            document.getElementById('evriApiSecret').value = '';
        }
    });

    submitBtn.addEventListener('click', function() {
        const id = document.getElementById('evriId').value;
        const payload = {
            api_key: document.getElementById('evriApiKey').value.trim() || null,
            api_secret: document.getElementById('evriApiSecret').value.trim() || null,
            client_id: document.getElementById('evriClientId').value.trim() || null,
            client_name: document.getElementById('evriClientName').value.trim() || null,
            child_client_id: document.getElementById('evriChildClientId').value.trim() || null,
            child_client_name: document.getElementById('evriChildClientName').value.trim() || null,
        };
        const isEdit = !!id;
        const url = isEdit ? baseUrl + '/' + id : baseUrl;
        const method = isEdit ? 'PUT' : 'POST';

        if (!isEdit && (!payload.api_key || !payload.api_secret || !payload.client_id || !payload.client_name)) {
            toastr.error('Please fill all required fields (Username, Password, Client ID, Client Name).');
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

    document.querySelectorAll('.set-active-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(baseUrl + '/' + id + '/set-active', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({}),
            })
            .then(r => r.json())
            .then(function(data) {
                if (data.success) { toastr.success(data.message); window.location.reload(); }
                else toastr.error(data.message || 'Failed');
            })
            .catch(function() { toastr.error('Request failed'); });
        });
    });

    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const display = this.dataset.display || 'this account';

            Swal.fire({
                title: 'Are you sure?',
                text: 'Delete Evri account "' + display + '"? You won\'t be able to revert this.',
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

    document.querySelectorAll('.evri-toggle-secret').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cell = this.closest('td');
            var code = cell.querySelector('.evri-value-text');
            var icon = this.querySelector('i');
            if (!code || !icon) return;
            var masked = code.getAttribute('data-masked') || '••••••••';
            var value = code.getAttribute('data-value') || '—';
            if (code.textContent === masked) {
                code.textContent = value;
                icon.classList.remove('tabler-eye');
                icon.classList.add('tabler-eye-off');
                btn.setAttribute('title', (btn.getAttribute('title') || '').replace('Show Password', 'Hide Password'));
            } else {
                code.textContent = masked;
                icon.classList.remove('tabler-eye-off');
                icon.classList.add('tabler-eye');
                btn.setAttribute('title', (btn.getAttribute('title') || '').replace('Hide Password', 'Show Password'));
            }
        });
    });
})();
</script>
@endpush
