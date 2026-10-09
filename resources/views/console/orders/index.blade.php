@extends('console.layout.app')

@section('title', 'Orders')

@push('styles')
    <style>
        /* Hide default datatable export buttons */
        .dt-buttons {
            display: none !important;
        }

        /* Amazon return–linked orders */
        .returned-order {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }

        .returned-order:hover {
            background-color: #ffe69c !important;
        }

        /* Prestock-reserved orders — same highlight as Amazon return rows */
        .prestock-order {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }

        .prestock-order:hover {
            background-color: #ffe69c !important;
        }

        /* Amazon Prime orders */
        .amazon-prime-order {
            background-color: #dbeafe !important;
        }

        .amazon-prime-order:hover {
            background-color: #bfdbfe !important;
        }

        .nav-tabs {
            justify-content: center;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 1rem 2rem;
            margin: 0 0.5rem;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            min-width: 150px;
            text-align: center;
        }

        .nav-tabs .nav-link:hover {
            color: #0d6efd;
            background-color: #f8f9fa;
            border-bottom: 3px solid #dee2e6;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            background-color: #e7f3ff;
            font-weight: 600;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15);
        }

        .tab-content {
            padding-top: 1rem;
        }

        .card-body {
            padding: 2rem;
        }
    </style>
@endpush

@section('content')
    @php
        $shipstationEnabled = (bool) config('services.shipstation.enabled');
        $defaultShipstationSettingId = $shipstationEnabled
            ? ($shipstationSettings ?? collect())->first()?->id
            : null;
        $defaultShipstationSettingQuery = $defaultShipstationSettingId
            ? '&shipstation_setting_id=' . $defaultShipstationSettingId
            : '';
        $defaultSourceFilterQuery = ($defaultSourceFilter ?? 'all') !== 'all'
            ? '&source_filter=' . urlencode($defaultSourceFilter)
            : '';
    @endphp
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Orders Management</h5>
                </div>
                <div>
                    <button class="btn btn-primary d-none" id="createBatchBtn" data-bs-toggle="modal"
                        data-bs-target="#createBatchModal" onclick="setCreateBatchModalData()">Create
                        Batch</button>
                    <button class="btn btn-danger d-none" id="deleteBulkBtn" onclick="confirmBulkDelete()">Delete
                        Selected</button>
                        
                    {{-- <button class="btn btn-success me-2" onclick="exportCustomOrders()">
                        <i class="fas fa-file-excel me-1"></i>Export Orders
                    </button> --}}

                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportOrderModal">
                        <i class="fas fa-file-excel me-1"></i>Export Orders
                    </button>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addorderFileModal">Import
                        Orders</button>

                </div>
            </div>

            <!-- Material Type Tabs -->
            <div class="card-body">
                <!-- Single Shared Filters Card (Above Tabs) -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <!-- Order Status Filter -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold mb-2">Batch Status:</label>
                                <div class="btn-group" role="group" aria-label="Order Status Filter">
                                    <button type="button" class="btn btn-primary active" id="pending-btn"
                                        onclick="filterByStatus('pending')">
                                        <i class="fas fa-clock me-1"></i> Pending Orders
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="all-btn"
                                        onclick="filterByStatus('all')">
                                        <i class="fas fa-list me-1"></i> All Orders
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Advanced Filters (each control col-3 = 25% width) -->
                        <div class="row g-3">
                            <div class="col-3">
                                <label for="start_date" class="form-label fw-semibold">Order from date:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            <div class="col-3">
                                <label for="end_date" class="form-label fw-semibold">Order to date:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                            <div class="col-3">
                                <label for="scan_start_date" class="form-label fw-semibold">Scan from date:</label>
                                <input type="date" class="form-control" id="scan_start_date" name="scan_start_date">
                            </div>
                            <div class="col-3">
                                <label for="scan_end_date" class="form-label fw-semibold">Scan to date:</label>
                                <input type="date" class="form-control" id="scan_end_date" name="scan_end_date">
                            </div>
                            <div class="col-3">
                                <label for="ship_status" class="form-label fw-semibold">Shipment Status:</label>
                                <select id="ship_status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="awaiting_payment">Awaiting Payment</option>
                                    <option value="awaiting_shipment" selected>Awaiting Shipment</option>
                                    <option value="shipped">Shipped</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <label for="design_status" class="form-label fw-semibold">Design Status:</label>
                                <select id="design_status" class="form-select">
                                    <option value="">All</option>
                                    <option value="designed">Designed</option>
                                    <option value="not_designed">Not Designed</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <label for="return_status" class="form-label fw-semibold">Return / prestock:</label>
                                <select id="return_status" class="form-select">
                                    <option value="">All</option>
                                    <option value="returned">Returned</option>
                                    <option value="not_returned">Not Returned</option>
                                    <option value="prestock">Prestock</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <label for="stitcher_filter" class="form-label fw-semibold">Stitcher:</label>
                                <select id="stitcher_filter" class="form-select">
                                    <option value="">All stitchers</option>
                                    <option value="unassigned">Unassigned</option>
                                    @foreach ($stitchers ?? [] as $stitcher)
                                        <option value="{{ $stitcher->id }}">{{ $stitcher->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-3">
                                <label for="source_filter" class="form-label fw-semibold">Source:</label>
                                <select id="source_filter" class="form-select">
                                    <option value="all" {{ ($defaultSourceFilter ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                                    @foreach ($orderSourceOptions ?? [] as $value => $label)
                                        <option value="{{ $value }}" {{ ($defaultSourceFilter ?? '') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                    @if ($hasOrdersWithoutSource ?? false)
                                        <option value="other" {{ ($defaultSourceFilter ?? '') === 'other' ? 'selected' : '' }}>Others</option>
                                    @endif
                                </select>
                            </div>
                            @if ($shipstationEnabled)
                                <div class="col-3">
                                    <label for="shipstation_setting_filter" class="form-label fw-semibold">ShipStation account:</label>
                                    <select id="shipstation_setting_filter" class="form-select">
                                        @foreach ($shipstationSettings ?? [] as $account)
                                            <option value="{{ $account->id }}" {{ $loop->first ? 'selected' : '' }}>
                                                {{ $account->name ?: $account->client_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <!-- Filter Actions -->
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

                <ul class="nav nav-tabs" id="materialTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="carpet-tab" data-bs-toggle="tab" data-bs-target="#carpet"
                            type="button" role="tab" aria-controls="carpet" aria-selected="true">
                            Carpet Orders
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rubber-tab" data-bs-toggle="tab" data-bs-target="#rubber"
                            type="button" role="tab" aria-controls="rubber" aria-selected="false">
                            Rubber Orders
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="materialTabsContent">
                    <!-- Carpet Tab -->
                    <div class="tab-pane fade show active" id="carpet" role="tabpanel" aria-labelledby="carpet-tab">
                        <div class="mt-4">
                            <x-datatable id="carpetOrdersTable" checkbox-callback="onCheckboxCheck"
                                ajax="{{ route('console.orders.index') }}?material_type=Carpet&status_filter=pending&ship_status=awaiting_shipment{{ $defaultSourceFilterQuery }}{{ $defaultShipstationSettingQuery }}"
                                export export-filename="orders_carpet" :columns="[
                                    [
                                        'data' => 'checkbox',
                                        'name' => 'checkbox',
                                        'label' =>
                                            '<input type=\'checkbox\' id=\'select-all-carpet\' class=\'form-check-input dt-select-all\' aria-label=\'Select all carpet orders\'>',
                                        'orderable' => false,
                                        'searchable' => false,
                                        'raw' => true,
                                    ],
                                    [
                                        'data' => 'DT_RowIndex',
                                        'name' => 'DT_RowIndex',
                                        'label' => '#',
                                        'orderable' => false,
                                        'searchable' => false,
                                    ],
                                
                                    ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                                    ['data' => 'sku', 'name' => 'sku', 'label' => 'sku'],
                                    ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type'],
                                    ['data' => 'make_model', 'name' => 'make_model', 'label' => 'Make & Model'],
                                    ['data' => 'quantity', 'name' => 'quantity', 'label' => 'Quantity'],
                                    ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging'],
                                    ['data' => 'recipient_name', 'name' => 'recipient_name', 'label' => 'Recipient'],
                                    ['data' => 'address', 'name' => 'address', 'label' => 'Address'],
                                    ['data' => 'city', 'name' => 'city', 'label' => 'City'],
                                    ['data' => 'postal_code', 'name' => 'postal_code', 'label' => 'Postcode'],
                                    [
                                        'data' => 'date',
                                        'name' => 'date',
                                        'label' => 'Order date',
                                        'default_order' => 'desc',
                                    ],
                                    ['data' => 'scan_time', 'name' => 'scan_time', 'label' => 'Scan date'],
                                    ['data' => 'stitcher_name', 'name' => 'stitcher_name', 'label' => 'Stitcher'],
                                    ['data' => 'source', 'name' => 'source', 'label' => 'Source'],
                                    ['data' => 'status', 'name' => 'status', 'label' => 'Status'],
                                    ['data' => 'batch_status', 'name' => 'batch_status', 'label' => 'Batch Status'],
                                    [
                                        'data' => 'action',
                                        'name' => 'action',
                                        'label' => 'Action',
                                        'orderable' => false,
                                        'searchable' => false,
                                    ],
                                ]" />
                        </div>
                    </div>

                    <!-- Rubber Tab -->
                    <div class="tab-pane fade" id="rubber" role="tabpanel" aria-labelledby="rubber-tab">
                        <div class="mt-4">
                            <x-datatable id="rubberOrdersTable" checkbox-callback="onCheckboxCheck"
                                ajax="{{ route('console.orders.index') }}?material_type=Rubber&status_filter=pending&ship_status=awaiting_shipment{{ $defaultSourceFilterQuery }}{{ $defaultShipstationSettingQuery }}"
                                export export-filename="orders_rubber" :columns="[
                                    [
                                        'data' => 'checkbox',
                                        'name' => 'checkbox',
                                        'label' =>
                                            '<input type=\'checkbox\' id=\'select-all-rubber\' class=\'form-check-input dt-select-all\' aria-label=\'Select all rubber orders\'>',
                                        'orderable' => false,
                                        'searchable' => false,
                                        'raw' => true,
                                    ],
                                    [
                                        'data' => 'DT_RowIndex',
                                        'name' => 'DT_RowIndex',
                                        'label' => '#',
                                        'orderable' => false,
                                        'searchable' => false,
                                    ],
                                
                                    ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                                    ['data' => 'sku', 'name' => 'sku', 'label' => 'sku'],
                                    ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type'],
                                    ['data' => 'make_model', 'name' => 'make_model', 'label' => 'Make & Model'],
                                    ['data' => 'quantity', 'name' => 'quantity', 'label' => 'Quantity'],
                                    ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging'],
                                    ['data' => 'recipient_name', 'name' => 'recipient_name', 'label' => 'Recipient'],
                                    ['data' => 'address', 'name' => 'address', 'label' => 'Address'],
                                    ['data' => 'city', 'name' => 'city', 'label' => 'City'],
                                    ['data' => 'postal_code', 'name' => 'postal_code', 'label' => 'Postcode'],
                                    [
                                        'data' => 'date',
                                        'name' => 'date',
                                        'label' => 'Order date',
                                        'default_order' => 'desc',
                                    ],
                                    ['data' => 'scan_time', 'name' => 'scan_time', 'label' => 'Scan date'],
                                    ['data' => 'stitcher_name', 'name' => 'stitcher_name', 'label' => 'Stitcher'],
                                    ['data' => 'source', 'name' => 'source', 'label' => 'Source'],
                                    ['data' => 'status', 'name' => 'status', 'label' => 'Status'],
                                    ['data' => 'batch_status', 'name' => 'batch_status', 'label' => 'Batch Status'],
                                    [
                                        'data' => 'action',
                                        'name' => 'action',
                                        'label' => 'Action',
                                        'orderable' => false,
                                        'searchable' => false,
                                    ],
                                ]" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.orders.partials.import-order-modal')
    @include('console.orders.partials.export-order-modal')
    @include('console.orders.partials.delete-order-modal')
    @include('console.orders.partials.create-batch-modal')
    @include('console.orders.partials.return-details-modal')
    @include('console.orders.partials.prestock-match-modal')
@endpush

@push('scripts')
    <script>
        let currentMaterialType = 'Carpet'; // Default to Carpet tab
        let pendingDeleteElement = null;
        let pendingBulkDelete = false;
        let verifiedBulkDeletePassword = null;
        const shipstationEnabled = @json($shipstationEnabled);
        const defaultShipstationSettingId = @json($defaultShipstationSettingId);
        const defaultSourceFilter = @json($defaultSourceFilter ?? 'all');

        function getShipstationSettingFilter() {
            if (!shipstationEnabled) {
                return '';
            }
            const el = document.getElementById('shipstation_setting_filter');

            return el ? el.value : '';
        }

        function deleteOrder(event, element) {
            event.preventDefault();
            pendingBulkDelete = false;
            pendingDeleteElement = element;

            const deleteModalEl = document.getElementById('deleteOrderPasswordModal');
            if (!deleteModalEl) {
                Swal.fire('Error!', 'Delete password modal is not available.', 'error');
                return;
            }

            const modalTitle = deleteModalEl.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = 'Delete Order';
            }

            const modalInstance = new bootstrap.Modal(deleteModalEl);
            modalInstance.show();
        }

        function executeDeleteOrder(element) {
            if (!element) {
                Swal.fire('Error!', 'No order selected for delete.', 'error');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: 'This record will be deleted permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const id = element.getAttribute('data-id');
                    fetch("{{ route('console.orders.destroy', ':id') }}".replace(':id', id), {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', data.message, 'success');
                                // Reload both tables
                                $('#carpetOrdersTable').DataTable().ajax.reload();
                                $('#rubberOrdersTable').DataTable().ajax.reload();
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

        (function initDeletePasswordGate() {
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
                    pendingDeleteElement = null;
                    pendingBulkDelete = false;
                    verifiedBulkDeletePassword = null;
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

                    if (pendingBulkDelete) {
                        pendingBulkDelete = false;
                        verifiedBulkDeletePassword = entered;
                        executeBulkDeleteOrders();
                        return;
                    }

                    const elementToDelete = pendingDeleteElement;
                    pendingDeleteElement = null;
                    executeDeleteOrder(elementToDelete);
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

        function unutilizeReturn(element) {
            const orderId = element.getAttribute('data-order-id');
            const matchType = element.getAttribute('data-match-type') || 'amazon';
            const unlinkText = matchType === 'prestock'
                ? 'This will restore one unit to prestock and unlink this order from the prestock line.'
                : 'This will unlink the order from the return.';

            Swal.fire({
                title: 'Are you sure?',
                text: unlinkText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, unutilize it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('{{ route('console.orders.unutilizeReturn') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                order_id: orderId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Success!', data.message, 'success');
                                // Reload both tables
                                $('#carpetOrdersTable').DataTable().ajax.reload();
                                $('#rubberOrdersTable').DataTable().ajax.reload();
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

        function onCheckboxCheck(tableElement) {
            const checkedCount = tableElement.querySelectorAll('tbody input[type="checkbox"]:checked').length;
            const deleteBulkBtn = document.getElementById('deleteBulkBtn');
            const createBatchBtn = document.getElementById('createBatchBtn');
            if (createBatchBtn) {
                createBatchBtn.classList.toggle('d-none', checkedCount < 1);
            }
            if (deleteBulkBtn) {
                deleteBulkBtn.classList.toggle('d-none', checkedCount === 0);
            }
        }

        function setCreateBatchModalData() {
            const parentElement = document.getElementById('createBatchModal');
            const ordersContainer = parentElement.querySelector('#ordersContainer');
            const orderIdInput = parentElement.querySelector('#orderId');

            generateUniqueBatchIdentifiers(parentElement)

            // Clear previous content
            ordersContainer.innerHTML = '';
            orderIdInput.value = '';

            const selectedOrderIds = [];
            const selectedProducts = [];

            // Get checked checkboxes from both tables
            const activeTab = document.querySelector('.tab-pane.active');
            const checkedCheckboxes = activeTab.querySelectorAll('.row-checkbox:checked');

            // First, create all cards with placeholder images
            checkedCheckboxes.forEach(cb => {
                const order_ids = cb.value;
                const orderId = cb.dataset.orderId;
                const designType = cb.dataset.designType;
                const product = cb.dataset.product;
                const sku = cb.dataset.sku;

                selectedOrderIds.push(order_ids);
                selectedProducts.push(sku);

                const cardHTML = `
                    <div class="col">
                        <div class="card batch-card h-100" data-product="${product}" data-sku="${sku}" >
                            <div class="card-img-top-container" style="height: 200px; overflow: hidden;">
                                <img src="{{ asset('themes/console/assets/img/pages/mat.jpg') }}" class="img-fluid w-100 h-100 product-image" style="object-fit: cover;" alt="product-image">
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="order-detail-content flex-grow-1">
                                    <div class="order-item"><p>Order Id: <span style="margin-left:10px">${orderId}</span></p></div>
                                    <div class="order-item"><p>Design Type: <span style="margin-left:10px">${sku}</span></p></div>
                                    <div class="order-item"><p>Product: <span style="margin-left:10px" class="product-ellipsis">${product}</span></p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                ordersContainer.insertAdjacentHTML('beforeend', cardHTML);
            });

            // Then, fetch and update product images dynamically
            if (selectedProducts.length > 0) {
                fetchProductImages(selectedProducts);
            }

            orderIdInput.value = selectedOrderIds.join(',');

            // Check which products have files uploaded
            if (selectedProducts.length > 0) {
                checkProductFiles(selectedProducts);
            }
        }

        function fetchProductImages(products) {
            // Create a unique list of products
            const uniqueProducts = [...new Set(products)];

            // Fetch product images for each unique product
            uniqueProducts.forEach(product => {
                fetch("{{ route('console.products.getProductImage') }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            product_sku: product
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.image_url) {
                            // Update all cards with this product name
                            const cards = document.querySelectorAll(`.batch-card[data-sku="${product}"]`);
                            cards.forEach(card => {
                                const img = card.querySelector('.product-image');
                                if (img) {
                                    img.src = data.image_url;
                                    // Ensure the image maintains consistent styling
                                    img.style.objectFit = 'cover';
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching product image for', product, ':', error);
                    });
            });
        }

        function checkProductFiles(products) {
            fetch("{{ route('console.batchManagement.batches.checkProductFiles') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        products: products
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const productFileStatus = data.data;

                        // Apply red border to products without files
                        Object.keys(productFileStatus).forEach(productName => {
                            if (!productFileStatus[productName]) {
                                const card = document.querySelector(
                                    `.batch-card[data-product="${productName}"]`);
                                if (card) {
                                    card.style.border = '2px solid #dc3545';
                                    card.style.borderRadius = '8px';

                                    // Add a warning indicator
                                    const warningDiv = document.createElement('div');
                                    warningDiv.className = 'position-absolute top-0 end-0 m-2';
                                    warningDiv.innerHTML =
                                        '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> No File</span>';
                                    card.style.position = 'relative';
                                    card.appendChild(warningDiv);
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error checking product files:', error);
                });
        }

        function generateUniqueBatchIdentifiers(parentElement) {
            fetch("{{ route('console.batchManagement.batches.generateUniqueIdentifiers') }}")
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const data = result.data;
                        parentElement.querySelector('#batchid').value = data.batch_id;
                        parentElement.querySelector('#batchname').value = data.batch_name;
                    } else {
                        toastr.error('Invalid response from server.');
                    }
                })
                .catch(() => {
                    toastr.error('Something went wrong. Try again later.');
                });
        }

        function handleCreateBatchFormSubmit(typeSetting = false) {
            const parentElement = document.getElementById('createBatchModal');

            fetch("{{ route('console.batchManagement.batches.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: parentElement.querySelector('#batchid').value,
                        batch_name: parentElement.querySelector('#batchname').value,
                        order_ids: parentElement.querySelector('#orderId').value.split(
                            ','),
                        type_setting: typeSetting,
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(parentElement).hide();
                        if (data.redirect_url) {
                            toastr.warning('Starting Design Process.');
                            toastr.success('Batch created successfully.');
                            setTimeout(() => {
                                window.location.href = data.redirect_url;
                            }, 2700);
                        } else {
                            // Reload both tables
                            $('#carpetOrdersTable').DataTable().ajax.reload();
                            $('#rubberOrdersTable').DataTable().ajax.reload();
                            Swal.fire('Success!', data.message, 'success');
                            document.getElementById('createBatchBtn').classList.add('d-none');
                        }
                    } else {
                        let errorMessage = '';
                        if (data.errors) {
                            for (const [key, value] of Object.entries(data.errors)) {
                                errorMessage += `${value.join(' ')}<br>`;
                            }
                        } else {
                            errorMessage = data.message || 'An error occurred while creating the batch.';
                        }

                        toastr.error(errorMessage);
                    }
                })
                .catch(() => {
                    toastr.error('Network error. Please try again later.');
                });
        }

        // Shared Filter Functions
        function filterByStatus(status) {
            // Update button states
            const pendingBtn = document.getElementById('pending-btn');
            const allBtn = document.getElementById('all-btn');

            if (status === 'pending') {
                pendingBtn.classList.remove('btn-outline-primary');
                pendingBtn.classList.add('btn-primary', 'active');
                allBtn.classList.remove('btn-primary', 'active');
                allBtn.classList.add('btn-outline-primary');
            } else {
                allBtn.classList.remove('btn-outline-primary');
                allBtn.classList.add('btn-primary', 'active');
                pendingBtn.classList.remove('btn-primary', 'active');
                pendingBtn.classList.add('btn-outline-primary');
            }

            // Apply filters to both tables
            applyFilters();
        }

        function applyFilters() {
            const pendingBtn = document.getElementById('pending-btn');
            const statusFilter = pendingBtn.classList.contains('active') ? 'pending' : 'all';
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const scanStartDate = document.getElementById('scan_start_date').value;
            const scanEndDate = document.getElementById('scan_end_date').value;
            const shipStatus = document.getElementById('ship_status').value;
            const designStatus = document.getElementById('design_status').value;
            const returnStatus = document.getElementById('return_status').value;
            const stitcherFilter = document.getElementById('stitcher_filter').value;
            const sourceFilter = document.getElementById('source_filter').value;
            const shipstationSettingFilter = getShipstationSettingFilter();

            // Apply to Carpet table
            let carpetUrl = `{{ route('console.orders.index') }}?material_type=Carpet`;
            if (statusFilter === 'pending') carpetUrl += `&status_filter=pending`;
            if (startDate) carpetUrl += `&start_date=${startDate}`;
            if (endDate) carpetUrl += `&end_date=${endDate}`;
            if (scanStartDate) carpetUrl += `&scan_start_date=${scanStartDate}`;
            if (scanEndDate) carpetUrl += `&scan_end_date=${scanEndDate}`;
            if (shipStatus) carpetUrl += `&ship_status=${encodeURIComponent(shipStatus)}`;
            if (designStatus) carpetUrl += `&design_status=${encodeURIComponent(designStatus)}`;
            if (returnStatus) carpetUrl += `&return_status=${encodeURIComponent(returnStatus)}`;
            if (stitcherFilter) carpetUrl += `&stitcher_id=${encodeURIComponent(stitcherFilter)}`;
            if (sourceFilter) carpetUrl += `&source_filter=${encodeURIComponent(sourceFilter)}`;
            if (shipstationSettingFilter) carpetUrl += `&shipstation_setting_id=${encodeURIComponent(shipstationSettingFilter)}`;
            $('#carpetOrdersTable').DataTable().ajax.url(carpetUrl).load();

            // Apply to Rubber table
            let rubberUrl = `{{ route('console.orders.index') }}?material_type=Rubber`;
            if (statusFilter === 'pending') rubberUrl += `&status_filter=pending`;
            if (startDate) rubberUrl += `&start_date=${startDate}`;
            if (endDate) rubberUrl += `&end_date=${endDate}`;
            if (scanStartDate) rubberUrl += `&scan_start_date=${scanStartDate}`;
            if (scanEndDate) rubberUrl += `&scan_end_date=${scanEndDate}`;
            if (shipStatus) rubberUrl += `&ship_status=${encodeURIComponent(shipStatus)}`;
            if (designStatus) rubberUrl += `&design_status=${encodeURIComponent(designStatus)}`;
            if (returnStatus) rubberUrl += `&return_status=${encodeURIComponent(returnStatus)}`;
            if (stitcherFilter) rubberUrl += `&stitcher_id=${encodeURIComponent(stitcherFilter)}`;
            if (sourceFilter) rubberUrl += `&source_filter=${encodeURIComponent(sourceFilter)}`;
            if (shipstationSettingFilter) rubberUrl += `&shipstation_setting_id=${encodeURIComponent(shipstationSettingFilter)}`;
            $('#rubberOrdersTable').DataTable().ajax.url(rubberUrl).load();

            toastr.success('Filters applied successfully.');
        }

        function clearFilters() {
            // Clear all inputs
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('scan_start_date').value = '';
            document.getElementById('scan_end_date').value = '';
            document.getElementById('ship_status').value = '';
            document.getElementById('design_status').value = '';
            document.getElementById('return_status').value = '';
            document.getElementById('stitcher_filter').value = '';
            document.getElementById('source_filter').value = defaultSourceFilter;
            if (shipstationEnabled) {
                const shipstationFilterEl = document.getElementById('shipstation_setting_filter');
                if (shipstationFilterEl) {
                    shipstationFilterEl.value = defaultShipstationSettingId ?? '';
                }
            }

            // Reset to pending status
            filterByStatus('pending');

            toastr.info('Filters cleared.');
        }


        if (document.getElementById('addorderFileForm')) {
            // Import form handling
            document.getElementById('addorderFileForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
                submitBtn.disabled = true;

                fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(data => {
                                toastr.error(data.message || 'Import failed. No orders were saved.');
                            }).catch(() => {
                                toastr.error('Import failed. No orders were saved.');
                            });
                        }
                        return response.text();
                    })
                    .then(html => {
                        if (html === undefined) return;
                        toastr.success('Orders imported successfully!');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addorderFileModal'));
                        if (modal) modal.hide();
                        if (typeof currentMaterialType !== 'undefined') {
                            $('#carpetOrdersTable').DataTable().ajax.reload();
                            $('#rubberOrdersTable').DataTable().ajax.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Import error:', error);
                        toastr.error('Error importing orders. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            });
        }
        $(function() {
            // Handle tab changes
            $('#materialTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                const target = $(e.target).attr('data-bs-target');
                if (target === '#carpet') {
                    currentMaterialType = 'Carpet';
                } else if (target === '#rubber') {
                    currentMaterialType = 'Rubber';
                }
            });

            $(document).on('change', '#select-all-carpet', function() {
                const isChecked = this.checked;
                $('#carpetOrdersTable tbody input[type="checkbox"].row-checkbox').prop('checked',
                isChecked);
                onCheckboxCheck(document.getElementById('carpetOrdersTable'));
            });

            $(document).on('change', '#select-all-rubber', function() {
                const isChecked = this.checked;
                $('#rubberOrdersTable tbody input[type="checkbox"].row-checkbox').prop('checked',
                isChecked);
                onCheckboxCheck(document.getElementById('rubberOrdersTable'));
            });

            $(document).on('change', '#carpetOrdersTable tbody input[type="checkbox"].row-checkbox', function() {
                const totalCheckboxes = $('#carpetOrdersTable tbody input[type="checkbox"].row-checkbox')
                    .length;
                const checkedCheckboxes = $(
                    '#carpetOrdersTable tbody input[type="checkbox"].row-checkbox:checked').length;
                $('#select-all-carpet').prop('checked', totalCheckboxes === checkedCheckboxes);
                onCheckboxCheck(document.getElementById('carpetOrdersTable'));
            });

            $(document).on('change', '#rubberOrdersTable tbody input[type="checkbox"].row-checkbox', function() {
                const totalCheckboxes = $('#rubberOrdersTable tbody input[type="checkbox"].row-checkbox')
                    .length;
                const checkedCheckboxes = $(
                    '#rubberOrdersTable tbody input[type="checkbox"].row-checkbox:checked').length;
                $('#select-all-rubber').prop('checked', totalCheckboxes === checkedCheckboxes);
                onCheckboxCheck(document.getElementById('rubberOrdersTable'));
            });
        });

        // Custom Export functionality
        function exportCustomOrders() {
            // Get current active tab to determine material type
            const activeTab = document.querySelector('.tab-pane.active');
            let materialType = '';

            if (activeTab.id === 'carpet') {
                materialType = 'Carpet';
            } else if (activeTab.id === 'rubber') {
                materialType = 'Rubber';
            }

            // Build export URL with current filters
            const pendingBtn = document.getElementById('pending-btn');
            const statusFilter = pendingBtn.classList.contains('active') ? 'pending' : 'all';
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const scanStartDate = document.getElementById('scan_start_date').value;
            const scanEndDate = document.getElementById('scan_end_date').value;
            const shipStatus = document.getElementById('ship_status').value;
            const designStatus = document.getElementById('design_status').value;
            const returnStatus = document.getElementById('return_status').value;
            const stitcherFilter = document.getElementById('stitcher_filter').value;
            const sourceFilter = document.getElementById('source_filter').value;
            const shipstationSettingFilter = getShipstationSettingFilter();

            let exportUrl = '{{ route('console.orders.customExport') }}?';
            if (materialType) exportUrl += `material_type=${materialType}&`;
            if (statusFilter === 'pending') exportUrl += `status_filter=pending&`;
            if (startDate) exportUrl += `start_date=${startDate}&`;
            if (endDate) exportUrl += `end_date=${endDate}&`;
            if (scanStartDate) exportUrl += `scan_start_date=${scanStartDate}&`;
            if (scanEndDate) exportUrl += `scan_end_date=${scanEndDate}&`;
            if (shipStatus) exportUrl += `ship_status=${encodeURIComponent(shipStatus)}&`;
            if (designStatus) exportUrl += `design_status=${encodeURIComponent(designStatus)}&`;
            if (returnStatus) exportUrl += `return_status=${encodeURIComponent(returnStatus)}&`;
            if (stitcherFilter) exportUrl += `stitcher_id=${encodeURIComponent(stitcherFilter)}&`;
            if (sourceFilter) exportUrl += `source_filter=${encodeURIComponent(sourceFilter)}&`;
            if (shipstationSettingFilter) exportUrl += `shipstation_setting_id=${encodeURIComponent(shipstationSettingFilter)}&`;

            // Remove trailing & or ?
            exportUrl = exportUrl.replace(/[&?]$/, '');

            // Trigger download
            window.location.href = exportUrl;

            toastr.success('Exporting orders...');
        }

        // Bulk delete functionality
        function confirmBulkDelete() {
            const activeTab = document.querySelector('.tab-pane.active');
            const checkedCheckboxes = activeTab.querySelectorAll('.row-checkbox:checked');
            const selectedCount = checkedCheckboxes.length;

            if (selectedCount === 0) {
                Swal.fire('No selection', 'Please select orders to delete.', 'warning');
                return;
            }

            pendingBulkDelete = true;
            pendingDeleteElement = null;

            const deleteModalEl = document.getElementById('deleteOrderPasswordModal');
            if (!deleteModalEl) {
                Swal.fire('Error!', 'Delete password modal is not available.', 'error');
                return;
            }

            const modalTitle = deleteModalEl.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Delete ${selectedCount} Selected Order(s)`;
            }

            const modalInstance = new bootstrap.Modal(deleteModalEl);
            modalInstance.show();
        }

        function executeBulkDeleteOrders() {
            if (!verifiedBulkDeletePassword) {
                Swal.fire('Error!', 'Password verification is required before deleting.', 'error');
                return;
            }

            const activeTab = document.querySelector('.tab-pane.active');
            const checkedCheckboxes = activeTab.querySelectorAll('.row-checkbox:checked');
            const orderIds = Array.from(checkedCheckboxes).map(cb => cb.value);

            if (orderIds.length === 0) {
                verifiedBulkDeletePassword = null;
                return;
            }

            const deletePassword = verifiedBulkDeletePassword;
            verifiedBulkDeletePassword = null;

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${orderIds.length} selected order(s). This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const deleteBulkBtn = document.getElementById('deleteBulkBtn');
                const originalText = deleteBulkBtn.textContent;
                deleteBulkBtn.textContent = 'Deleting...';
                deleteBulkBtn.disabled = true;

                fetch('{{ route('console.orders.bulkDestroy') }}', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            order_ids: orderIds,
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
                            Swal.fire('Deleted!', data.message || 'Orders deleted successfully.', 'success');
                            $('#carpetOrdersTable').DataTable().ajax.reload();
                            $('#rubberOrdersTable').DataTable().ajax.reload();
                            $('#select-all-carpet').prop('checked', false);
                            $('#select-all-rubber').prop('checked', false);
                            document.getElementById('createBatchBtn').classList.add('d-none');
                            document.getElementById('deleteBulkBtn').classList.add('d-none');
                        } else {
                            Swal.fire('Error!', data.message || 'Error deleting orders.', 'error');
                        }
                    })
                    .catch((error) => {
                        const message = error?.data?.message || 'An error occurred while deleting orders.';
                        Swal.fire('Error!', message, 'error');
                    })
                    .finally(() => {
                        deleteBulkBtn.textContent = originalText;
                        deleteBulkBtn.disabled = false;
                    });
            });
        }
    </script>
@endpush
