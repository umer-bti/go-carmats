@extends('console.layout.app')

@section('title', 'Deleted Orders')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Deleted Orders</h5>
                    <small class="text-muted">Orders that have been soft deleted. You can restore or permanently delete them.</small>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters Card -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="material_type_filter" class="form-label fw-semibold">Material Type:</label>
                                <select id="material_type_filter" class="form-select">
                                    <option value="">All</option>
                                    <option value="Carpet">Carpet</option>
                                    <option value="Rubber">Rubber</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date_filter" class="form-label fw-semibold">Deleted From:</label>
                                <input type="date" class="form-control" id="start_date_filter">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date_filter" class="form-label fw-semibold">Deleted To:</label>
                                <input type="date" class="form-control" id="end_date_filter">
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="clearFilters()">
                                    <i class="fas fa-times me-1"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deleted Orders Table -->
                <div class="mt-4">
                    <x-datatable id="deletedOrdersTable" ajax="{{ route('console.orders.deleted.index') }}" :columns="[
                        [
                            'data' => 'DT_RowIndex',
                            'name' => 'DT_RowIndex',
                            'label' => '#',
                            'orderable' => false,
                            'searchable' => false,
                        ],
                        ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                        ['data' => 'sku', 'name' => 'sku', 'label' => 'SKU'],
                        ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type'],
                        ['data' => 'make_model', 'name' => 'make_model', 'label' => 'Make & Model'],
                        ['data' => 'quantity', 'name' => 'quantity', 'label' => 'Quantity'],
                        ['data' => 'recipient_name', 'name' => 'recipient_name', 'label' => 'Recipient'],
                        ['data' => 'deleted_at_formatted', 'name' => 'deleted_at', 'label' => 'Deleted At', 'default_order' => 'desc'],
                        [
                            'data' => 'action',
                            'name' => 'action',
                            'label' => 'Action',
                            'orderable' => false,
                            'searchable' => false,
                            'raw' => true,
                        ],
                    ]" />
                </div>
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.orders.partials.delete-order-modal')
@endpush

@push('scripts')
    <script>
        let pendingPermanentDeleteElement = null;
        let verifiedPermanentDeletePassword = null;

        function applyFilters() {
            const materialType = document.getElementById('material_type_filter').value;
            const startDate = document.getElementById('start_date_filter').value;
            const endDate = document.getElementById('end_date_filter').value;

            let filterUrl = '{{ route('console.orders.deleted.index') }}';
            const params = [];
            
            if (materialType) params.push(`material_type=${encodeURIComponent(materialType)}`);
            if (startDate) params.push(`start_date=${startDate}`);
            if (endDate) params.push(`end_date=${endDate}`);
            
            if (params.length) filterUrl += `?${params.join('&')}`;

            $('#deletedOrdersTable').DataTable().ajax.url(filterUrl).load();
            toastr.success('Filters applied successfully.');
        }

        function clearFilters() {
            document.getElementById('material_type_filter').value = '';
            document.getElementById('start_date_filter').value = '';
            document.getElementById('end_date_filter').value = '';
            
            $('#deletedOrdersTable').DataTable().ajax.url('{{ route('console.orders.deleted.index') }}').load();
            toastr.info('Filters cleared.');
        }

        function restoreOrder(element) {
            const orderId = element.getAttribute('data-id');

            Swal.fire({
                title: 'Restore Order?',
                text: 'This will restore the order to the orders list.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, restore it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('{{ route('console.orders.deleted.restore') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: orderId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Restored!', data.message, 'success');
                            $('#deletedOrdersTable').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    });
                }
            });
        }

        function permanentDeleteOrder(element) {
            pendingPermanentDeleteElement = element;

            const deleteModalEl = document.getElementById('deleteOrderPasswordModal');
            if (!deleteModalEl) {
                Swal.fire('Error!', 'Delete password modal is not available.', 'error');
                return;
            }

            const modalInstance = new bootstrap.Modal(deleteModalEl);
            modalInstance.show();
        }

        function executePermanentDeleteOrder(element) {
            if (!element) {
                Swal.fire('Error!', 'No order selected for delete.', 'error');
                return;
            }

            if (!verifiedPermanentDeletePassword) {
                Swal.fire('Error!', 'Password verification is required before deleting.', 'error');
                return;
            }

            const orderId = element.getAttribute('data-id');
            const deletePassword = verifiedPermanentDeletePassword;
            verifiedPermanentDeletePassword = null;

            Swal.fire({
                title: 'Permanently Delete?',
                text: 'This action CANNOT be undone! The order will be permanently deleted.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete permanently!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                confirmButtonColor: '#d33',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('{{ route('console.orders.deleted.forceDelete') }}', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: orderId,
                            password: deletePassword
                        })
                    })
                    .then(async response => {
                        const data = await response.json();

                        if (!response.ok) {
                            throw { response, data };
                        }

                        return data;
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', data.message, 'success');
                            $('#deletedOrdersTable').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch((error) => {
                        const message = error?.data?.message || 'Something went wrong.';
                        Swal.fire('Error!', message, 'error');
                    });
                }
            });
        }

        (function initPermanentDeletePasswordGate() {
            const deleteModalEl = document.getElementById('deleteOrderPasswordModal');
            const passwordInput = document.getElementById('deleteOrderPassword');
            const passwordError = document.getElementById('deleteOrderPasswordError');
            const verifyBtn = document.getElementById('deleteOrderVerifyBtn');

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

            if (deleteModalEl) {
                deleteModalEl.addEventListener('show.bs.modal', resetModal);
                deleteModalEl.addEventListener('hidden.bs.modal', function() {
                    resetModal();
                    pendingPermanentDeleteElement = null;
                    verifiedPermanentDeletePassword = null;
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

                    const modalInstance = bootstrap.Modal.getInstance(deleteModalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    verifiedPermanentDeletePassword = entered;

                    const elementToDelete = pendingPermanentDeleteElement;
                    pendingPermanentDeleteElement = null;
                    executePermanentDeleteOrder(elementToDelete);
                });
            }

            if (passwordInput) {
                passwordInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && verifyBtn) {
                        e.preventDefault();
                        verifyBtn.click();
                    }
                });
            }
        })();
    </script>
@endpush

