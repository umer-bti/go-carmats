@extends('console.layout.app')

@section('title', 'Designs')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-datatable pt-0 m-5">
                <x-datatable id="designsTable" ajax="{{ route('console.batchManagement.completed.index') }}"
                    :columns="[
                        [
                            'data' => 'DT_RowIndex',
                            'name' => 'DT_RowIndex',
                            'label' => '#',
                            'orderable' => false,
                            'searchable' => false,
                        ],
                        [
                            'data' => 'batch_id',
                            'name' => 'batch.batch_id',
                            'label' => 'Batch ID',
                        ],
                        [
                            'data' => 'batch_name',
                            'name' => 'batch.batch_name',
                            'label' => 'Batch Name',
                        ],
                        [
                            'data' => 'products',
                            'name' => 'products',
                            'label' => 'Products',
                            'searchable' => false,
                        ],
                        [
                            'data' => 'status',
                            'name' => 'design_status',
                            'label' => 'Status',
                        ],
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
@endsection

@push('partials')
    @include('console.batch-management.batches.partials.show-batch-modal')
@endpush

@push('scripts')
    <script>
        function markAsIncomplete(element) {
            const batch_id = element.getAttribute('data-id');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This will mark the batch as incomplete and move it back to the Active Batches tab.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark it!',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('console.batchManagement.batches.markAsIncomplete') }}", {
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
                                // Reload the table to reflect the changes
                                $('#designsTable').DataTable().ajax.reload();
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
                                                <div class="order-item"><p>Product: <span style="margin-left:10px" class="product-ellipsis">${order.product}</span></p></div>
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
    </script>
@endpush
