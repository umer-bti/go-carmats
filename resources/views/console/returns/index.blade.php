@extends('console.layout.app')

@section('title', 'Returns Management')

@push('styles')
    <style>
        /* Highlight returns linked to orders */
        .linked-to-order {
            background-color: #d1f2eb !important;
            border-left: 4px solid #28a745 !important;
        }

        .linked-to-order:hover {
            background-color: #a8e6cf !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Returns Management</h5>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters Card -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <!-- Received Status Filter -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold mb-2">Status:</label>
                                <div class="btn-group" role="group" aria-label="Received Status Filter">
                                    <button type="button" class="btn  btn-outline-warning" id="pending-btn" onclick="filterByStatus('pending')">
                                        <i class="fas fa-clock me-1"></i> Pending
                                    </button>
                                    <button type="button" class="btn btn-outline-success" id="received-btn" onclick="filterByStatus('received')">
                                        <i class="fas fa-check-circle me-1"></i> Received
                                    </button>
                                    <button type="button" class="btn btn-primary active" id="all-btn" onclick="filterByStatus('all')">
                                        <i class="fas fa-list me-1"></i> All
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Advanced Filters -->
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="tracking" class="form-label fw-semibold">Tracking Number:</label>
                                <input type="text" class="form-control" id="tracking" placeholder="Enter tracking number">
                            </div>
                            <div class="col-md-3">
                                <label for="order_id" class="form-label fw-semibold">Order ID:</label>
                                <input type="text" class="form-control" id="order_id" placeholder="Enter order ID">
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label fw-semibold">From Date:</label>
                                <input type="date" class="form-control" id="start_date">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label fw-semibold">To Date:</label>
                                <input type="date" class="form-control" id="end_date">
                            </div>
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

                <!-- DataTable -->
                <div class="mt-4">
                    <x-datatable id="returnsTable" ajax="{{ route('console.returns.index') }}?received_status=all" :columns="[
                        [
                            'data' => 'DT_RowIndex',
                            'name' => 'DT_RowIndex',
                            'label' => '#',
                            'orderable' => false,
                            'searchable' => false,
                        ],
                        ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                        ['data' => 'tracking', 'name' => 'tracking', 'label' => 'Tracking Number'],
                        ['data' => 'item_name', 'name' => 'item_name', 'label' => 'Item Name'],
                        ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material'],
                        ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging'],
                        ['data' => 'return_request_date', 'name' => 'return_request_date', 'label' => 'Return Date', 'default_order' => 'desc'],
                        ['data' => 'reason', 'name' => 'reason', 'label' => 'Reason'],
                        ['data' => 'received_badge', 'name' => 'received_badge', 'label' => 'Status', 'raw' => true],
                        ['data' => 'notes', 'name' => 'notes', 'label' => 'Notes'],
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
    @include('console.returns.partials.mark-as-received-modal')
@endpush

@push('scripts')
    <script>
        function filterByStatus(status) {
            // Update button states
            const pendingBtn = document.getElementById('pending-btn');
            const receivedBtn = document.getElementById('received-btn');
            const allBtn = document.getElementById('all-btn');
            
            pendingBtn.classList.remove('btn-warning', 'active');
            pendingBtn.classList.add('btn-outline-warning');
            receivedBtn.classList.remove('btn-success', 'active');
            receivedBtn.classList.add('btn-outline-success');
            allBtn.classList.remove('btn-primary', 'active');
            allBtn.classList.add('btn-outline-primary');

            if (status === 'pending') {
                pendingBtn.classList.remove('btn-outline-warning');
                pendingBtn.classList.add('btn-warning', 'active');
            } else if (status === 'received') {
                receivedBtn.classList.remove('btn-outline-success');
                receivedBtn.classList.add('btn-success', 'active');
            } else {
                allBtn.classList.remove('btn-outline-primary');
                allBtn.classList.add('btn-primary', 'active');
            }
            
            // Build filter URL
            let filterUrl = '{{ route('console.returns.index') }}';
            
            if (status !== 'all') {
                filterUrl += `?received_status=${status}`;
            }

            // Include other filters
            const tracking = document.getElementById('tracking').value;
            const orderId = document.getElementById('order_id').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            const params = new URLSearchParams();
            if (status !== 'all') params.append('received_status', status);
            if (tracking) params.append('tracking', tracking);
            if (orderId) params.append('order_id', orderId);
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);

            filterUrl = '{{ route('console.returns.index') }}' + (params.toString() ? '?' + params.toString() : '');
            
            // Reload DataTable
            $('#returnsTable').DataTable().ajax.url(filterUrl).load();
        }

        function applyFilters() {
            const pendingBtn = document.getElementById('pending-btn');
            const receivedBtn = document.getElementById('received-btn');
            const status = pendingBtn.classList.contains('active') ? 'pending' : 
                          (receivedBtn.classList.contains('active') ? 'received' : 'all');

            const tracking = document.getElementById('tracking').value;
            const orderId = document.getElementById('order_id').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            const params = new URLSearchParams();
            if (status !== 'all') params.append('received_status', status);
            if (tracking) params.append('tracking', tracking);
            if (orderId) params.append('order_id', orderId);
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);

            const filterUrl = '{{ route('console.returns.index') }}' + (params.toString() ? '?' + params.toString() : '');
            
            $('#returnsTable').DataTable().ajax.url(filterUrl).load();
            toastr.success('Filters applied successfully.');
        }

        function clearFilters() {
            document.getElementById('tracking').value = '';
            document.getElementById('order_id').value = '';
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            
            // Reset to pending
            filterByStatus('pending');
            
            toastr.info('Filters cleared.');
        }

        function openMarkAsReceivedModal(element) {
            const returnId = element.getAttribute('data-id');
            const modal = document.getElementById('markAsReceivedModal');
            const form = modal.querySelector('form');
            
            form.setAttribute('data-return-id', returnId);
            form.querySelector('#notes').value = '';
            
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        function unmarkAsReceived(element) {
            const returnId = element.getAttribute('data-id');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This will unmark the return as received.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, unmark it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('{{ route('console.returns.unmarkAsReceived') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: returnId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success!', data.message, 'success');
                            $('#returnsTable').DataTable().ajax.reload();
                        } else {
                            toastr.error(data.message || 'Something went wrong.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        toastr.error('Something went wrong. Try again later.');
                    });
                }
            });
        }

        // Apply filter on Enter key for tracking number input
        document.addEventListener('DOMContentLoaded', function() {
            const trackingInput = document.getElementById('tracking');
            if (trackingInput) {
                trackingInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyFilters();
                    }
                });
            }
        });
    </script>
@endpush

