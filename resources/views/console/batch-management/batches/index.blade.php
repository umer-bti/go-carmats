@extends('console.layout.app')

@section('title', 'Batch List')

@push('styles')
<style>
    .nav-tabs .nav-link {
        font-weight: 500;
        color: #6c757d;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }
    
    .nav-tabs .nav-link:hover {
        color: #495057;
        border-bottom-color: #dee2e6;
    }
    
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background-color: transparent;
    }
    
    .card-header-tabs {
        margin-right: 0;
        margin-left: 0;
        border-bottom: 0;
    }
    
    .tab-content {
        padding: 1rem 0;
    }
</style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="batchTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                            Active Batches
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                            Completed Batches
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="batchTabsContent">
                    <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                        <div class="card-datatable pt-0">
                            <x-datatable id="activeBatchTable" ajax="{{ route('console.batchManagement.batches.index') }}?status=active" :columns="[
                                [
                                    'data' => 'DT_RowIndex',
                                    'name' => 'DT_RowIndex',
                                    'label' => '#',
                                    'orderable' => false,
                                    'searchable' => false,
                                ],
                                ['data' => 'batch_id', 'name' => 'batch_id', 'label' => 'Batch ID'],
                                ['data' => 'batch_name', 'name' => 'batch_name', 'label' => 'Batch Name'],
                                ['data' => 'orders_count', 'name' => 'orders_count', 'label' => 'Orders', 'searchable' => false],
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
                    <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                        <div class="card-datatable pt-0">
                            <table id="completedBatchTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Batch ID</th>
                                        <th>Batch Name</th>
                                        <th>Orders</th>
                                        <th style="min-width: 250px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.batch-management.batches.partials.show-batch-modal')
@endpush

@push('scripts')
    <script>
        // Function to initialize the completed batches table
        function initializeCompletedTable() {
            if (!$.fn.DataTable.isDataTable('#completedBatchTable')) {
                console.log('Initializing completed table');
                $('#completedBatchTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('console.batchManagement.batches.index') }}?status=completed",
                        type: 'GET'
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'batch_id', name: 'batch_id'},
                        {data: 'batch_name', name: 'batch_name'},
                        {data: 'orders_count', name: 'orders_count',searchable:false},
                        {
                            data: 'action', 
                            name: 'action', 
                            orderable: false, 
                            searchable: false,
                            render: function(data) {
                                return data; // Ensure HTML is rendered properly
                            }
                        }
                    ],
                    order: [[1, 'desc']],
                    pageLength: 10,
                    responsive: true,
                    columnDefs: [
                        {
                            targets: 4, // Action column index
                            className: 'dt-body-center',
                            width: '250px',
                            defaultContent: '<div class="text-center">No actions available</div>',
                            createdCell: function(td, cellData, rowData, row, col) {
                                // Ensure HTML is rendered properly
                                if (cellData) {
                                    $(td).html(cellData);
                                }
                            }
                        }
                    ],
                    drawCallback: function() {
                        console.log('Table redrawn - checking action column');
                        // Check if action column is empty and try to fix it
                        if ($('#completedBatchTable tbody tr').length > 0) {
                            $('#completedBatchTable tbody tr').each(function() {
                                const actionCell = $(this).find('td:last-child');
                                if (actionCell.text().trim() === '' || !actionCell.html().includes('btn')) {
                                    console.log('Empty action cell found, reloading table');
                                    setTimeout(() => $('#completedBatchTable').DataTable().ajax.reload(), 5000);
                                    return false; // Break the loop
                                }
                            });
                        }
                    }
                });
            }
        }
        
        // Handle tab switching and reload tables when needed
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = document.getElementById('active-tab');
            const completedTab = document.getElementById('completed-tab');
            
            // Initialize the completed table immediately
            initializeCompletedTable();
            
            // Reload completed table when switching to completed tab
            completedTab.addEventListener('click', function() {
                console.log('Switching to completed tab');
                setTimeout(() => {
                    if ($.fn.DataTable.isDataTable('#completedBatchTable')) {
                        $('#completedBatchTable').DataTable().ajax.reload();
                    } else {
                        initializeCompletedTable();
                    }
                }, 100);
            });
            
            // Reload active table when switching to active tab
            activeTab.addEventListener('click', function() {
                console.log('Switching to active tab');
                setTimeout(() => {
                    if ($.fn.DataTable.isDataTable('#activeBatchTable')) {
                        $('#activeBatchTable').DataTable().ajax.reload();
                    } else {
                        console.log('Active table not initialized yet');
                    }
                }, 100);
            });
        });

        function setShowBatchModalData(element) {
            const id = element.getAttribute('data-id')
            const modalElement = document.getElementById('showBatchModal')

            fetch("{{ route('console.batchManagement.batches.show', ':id') }}".replace(':id', id))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {

                        const batch = data.batch;

                        modalElement.querySelector('#batchid').value = batch.batch_id;
                        modalElement.querySelector('#batchname').value = batch.batch_name;

                        const ordersContainer = modalElement.querySelector('#ordersContainer');
                        ordersContainer.innerHTML = '';

                        batch.orders.forEach(order => {
                            const cardHTML = `
                                <div class="col">
                                    <div class="card batch-card">
                                        <img src="${order.design_file_url || '{{ asset('themes/console/assets/img/pages/mat.jpg') }}'}" class="img-fluid w-100 h-auto" alt="product-image">
                                        <div class="card-body">
                                            <div class="order-detail-content">
                                                <div class="order-item"><p>Order Id: <span style="margin-left:10px">${order.order_id}</span></p></div>
                                                <div class="order-item"><p>Design Type: <span style="margin-left:10px">${order.material_type}</span></p></div>
                                                <div class="order-item"><p>Product: <span style="margin-left:10px" class="product-ellipsis">${order.make_model}</span></p></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;

                            ordersContainer.insertAdjacentHTML('beforeend', cardHTML);
                        });

                        new bootstrap.Modal(modalElement).show();
                    }
                })
                .catch(error => {
                    toastr.error('Something went wrong.', 'Error');
                    console.error('Error:', error);
                });
        }

        function markAsCompleted(element) {
            const batch_id = element.getAttribute('data-id');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This will mark the batch as completed and move it to the Completed Batches tab.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark it!',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('console.batchManagement.batches.markAsCompleted') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                batch_id: batch_id
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Marked!', data.message, 'success');

                                // Reload both tables
                                $('#activeBatchTable').DataTable().ajax.reload();
                                $('#completedBatchTable').DataTable().ajax.reload();
                            } else {
                                Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Failed to process the request.', 'error');
                        });
                }
            });
        }

        function downloadBatchFiles(element) {
            const batchId = element.getAttribute('data-id');
            
            // Show loading message
            Swal.fire({
                title: 'Loading files...',
                text: 'Please wait while we retrieve the files.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Direct download
            const url = "{{ route('console.batchManagement.batches.downloadFiles', ':id') }}".replace(':id', batchId);

            Swal.close();
            window.location.href = url; // Trigger download directly
        }

        function markAsProcessed(element) {
            const batch_id = element.getAttribute('data-id');

            Swal.fire({
                title: 'Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark it!',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('console.batchManagement.batches.markAsProcessed') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            batch_id: batch_id,
                        }),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Marked!', data.message, 'success');
                                $('#activeBatchTable').DataTable().ajax.reload();
                            } else {
                                Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Failed to process the request.', 'error');
                        });
                }
            });
        }

        function exportBatchOrders(element) {
            const batchId = element.getAttribute('data-id');
            const batchName = element.getAttribute('data-batch-name');
            
            // Show loading message
            toastr.info('Preparing export for ' + batchName + '...');
            
            // Trigger download
            const url = "{{ route('console.batchManagement.batches.exportOrders', ':id') }}".replace(':id', batchId);
            window.location.href = url;
            
            // Show success message after a short delay
            setTimeout(() => {
                toastr.success('Export started successfully!');
            }, 500);
        }

    </script>
@endpush