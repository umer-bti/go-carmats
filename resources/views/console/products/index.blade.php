@extends('console.layout.app')

@section('title', 'Product Settings')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" id="exportDxfBtn" style="display: none;" onclick="exportSelectedDxfFiles()">
                        <i class="ti ti-download me-1"></i>Export DXF Files (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-primary" onclick="setDataAddOrEditProductFileModal()" data-bs-toggle="modal"
                        data-bs-target="#addOrEditProductFileModal">
                        Add New Product File
                    </button>
                </div>
            </div>
            <div class="card-datatable pt-0 m-5">
                <x-datatable id="product-table" ajax="{{ route('console.products.index') }}" :columns="[
                    [
                        'data' => 'checkbox',
                        'name' => 'checkbox',
                        'label' => '<input type=\'checkbox\' id=\'selectAllProducts\'>',
                        'raw' => true,
                        'orderable' => false,
                        'searchable' => false,
                        'class' => 'cell-fit',
                    ],
                    [
                        'data' => 'DT_RowIndex',
                        'name' => 'DT_RowIndex',
                        'label' => '#',
                        'orderable' => false,
                        'searchable' => false,
                    ],
                    ['data' => 'image', 'name' => 'image', 'label' => 'Image'],
                    ['data' => 'name', 'name' => 'name', 'label' => 'Name'],
                    ['data' => 'no_of_clips', 'name' => 'no_of_clips', 'label' => 'No. Of Clips'],
                    ['data' => 'no_of_mats', 'name' => 'no_of_mats', 'label' => 'No. Of Mats'],
                    ['data' => 'code', 'name' => 'code', 'label' => 'Code'],
                    ['data' => 'description', 'name' => 'description', 'label' => 'Note'],
                    [
                        'data' => 'action',
                        'name' => 'action',
                        'label' => 'Action',
                        'raw' => true,
                        'class' => 'cell-fit',
                        'orderable' => false,
                        'searchable' => false,
                    ],
                ]" />
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.products.partials.add-or-edit-product-file-modal')
@endpush

@push('scripts')
    <script src="{{ asset('themes/console/assets/vendor/libs/select2/select2.js') }}"></script>
    <script>
        // Handle checkbox selection
        $(document).ready(function() {
            // Handle select all checkbox
            $(document).on('change', '#selectAllProducts', function() {
                const isChecked = $(this).prop('checked');
                $('.product-checkbox').prop('checked', isChecked);
                updateExportButton();
            });

            // Handle individual checkbox change
            $(document).on('change', '.product-checkbox', function() {
                updateExportButton();
                
                // Update select all checkbox state
                const totalCheckboxes = $('.product-checkbox').length;
                const checkedCheckboxes = $('.product-checkbox:checked').length;
                $('#selectAllProducts').prop('checked', totalCheckboxes === checkedCheckboxes);
            });

            // Update export button on table redraw
            $('#product-table').on('draw.dt', function() {
                updateExportButton();
            });
        });

        function updateExportButton() {
            const selectedCount = $('.product-checkbox:checked').length;
            $('#selectedCount').text(selectedCount);
            
            if (selectedCount > 0) {
                $('#exportDxfBtn').show();
            } else {
                $('#exportDxfBtn').hide();
            }
        }

        function exportSelectedDxfFiles() {
            const selectedIds = [];
            $('.product-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                toastr.warning('Please select at least one product to export.');
                return;
            }

            // Show loading state
            const exportBtn = $('#exportDxfBtn');
            const originalHtml = exportBtn.html();
            exportBtn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i>Exporting...');

            // Create a form and submit it to trigger download
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('console.products.exportDxf') }}';
            form.style.display = 'none';

            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);

            // Add product IDs
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset button state after a delay
            setTimeout(() => {
                exportBtn.prop('disabled', false).html(originalHtml);
                toastr.success('DXF files exported successfully!');
            }, 2000);
        }

        function setDataAddOrEditProductFileModal(element) {
            const modalElement = document.getElementById('addOrEditProductFileModal');
            const form = modalElement.querySelector('form');
            const fileInput = modalElement.querySelector('#file');
            const fileLabel = modalElement.querySelector('label[for="file"]');

            if (element) {
                const productId = element.getAttribute('data-id');
                // Fetch the product data from backend
                fetch(`{{ route('console.products.show') }}/?id=${productId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const product = result.data;
                            form.querySelector('#name').value = product.name || '';
                            form.querySelector('#description').value = product.description || '';
                            form.querySelector('#noOfClips').value = product.no_of_clips || '';
                            form.querySelector('#noOfMats').value = product.no_of_mats || '';
                            form.querySelector('#code').value = product.code || '';
                            form.querySelector('#file').value = '';
                            // In edit mode, make file optional
                            if (fileInput) fileInput.required = false;
                            if (fileLabel) fileLabel.textContent = 'Design File (optional)';
                            // Pass SKUs to modal for prefill on shown event
                            const skus = Array.isArray(product.skus) ? product.skus : [];
                            modalElement.dataset.prefillSkus = JSON.stringify(skus);
                            form.setAttribute('data-id', product.id);

                            const modal = new bootstrap.Modal(modalElement);
                            modal.show();
                        } else {
                            toastr.error(result.message || 'Failed to load product.');
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        toastr.error('Failed to load product data.');
                    });
            } else {
                form.removeAttribute('data-id');
                form.reset();
                // In create mode, make file required
                if (fileInput) fileInput.required = true;
                if (fileLabel) fileLabel.textContent = 'Design File';
                // Clear SKUs if Select2 is initialized
                const $skus = $('#skus');
                if ($skus.length) {
                    $skus.val([]).trigger('change');
                }
                delete modalElement.dataset.prefillSkus;
            }
        }




        function deleteProduct(element) {
            const productId = element.getAttribute('data-id');

            requestAccessPassword({
                title: 'Delete Product',
                onVerified(password) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'This will permanently delete the product.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        fetch('{{ route('console.products.destroy') }}', {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id: productId,
                                    password
                                })
                            })
                            .then(parseProtectedDeleteResponse)
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Deleted!', data.message, 'success');
                                    $('#product-table').DataTable().ajax.reload();
                                } else {
                                    Swal.fire('Error!', data.message || 'Something went wrong.', 'error');
                                }
                            })
                            .catch(error => showProtectedDeleteError(error, 'Something went wrong. Try again later.'));
                    });
                }
            });
        }
    </script>
@endpush
