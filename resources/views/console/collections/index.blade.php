@extends('console.layout.app')

@section('title', 'Collections')

@push('styles')
    <style>
        .collections-product-field {
            min-width: 0;
        }

        .collections-product-select {
            width: 100%;
            max-width: 100%;
        }

        #product_filter + .select2,
        #product_setting_id + .select2 {
            width: 100% !important;
            max-width: 100%;
        }

        #product_filter + .select2 .select2-selection__rendered,
        #product_setting_id + .select2 .select2-selection__rendered {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Collections</h5>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="setDataAddOrEditCollectionModal()" data-bs-toggle="modal"
                        data-bs-target="#addOrEditCollectionModal">
                        Add New Collection
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body mt-2">
                        <div class="row g-3">
                            <div class="col-md-4 collections-product-field">
                                <label for="product_filter" class="form-label fw-semibold">Product:</label>
                                <select id="product_filter" class="form-select collections-product-select">
                                    <option value="">All Products</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" title="{{ $product->name }}" data-full-name="{{ $product->name }}">
                                            {{ \Illuminate\Support\Str::limit($product->name, 50, '***') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="material_type_filter" class="form-label fw-semibold">Material Type:</label>
                                <select id="material_type_filter" class="form-select">
                                    <option value="">All Materials</option>
                                    <option value="Carpet">Carpet</option>
                                    <option value="Rubber">Rubber</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="edging_filter" class="form-label fw-semibold">Edging:</label>
                                <select id="edging_filter" class="form-select">
                                    <option value="">All Edging Colors</option>
                                    <option value="Blue">Blue</option>
                                    <option value="Grey">Grey</option>
                                    <option value="Black">Black</option>
                                    <option value="Green">Green</option>
                                    <option value="White">White</option>
                                    <option value="Red">Red</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="from_date_filter" class="form-label fw-semibold">From Date:</label>
                                <input type="date" id="from_date_filter" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="to_date_filter" class="form-label fw-semibold">To Date:</label>
                                <input type="date" id="to_date_filter" class="form-control">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary" onclick="applyCollectionFilters()">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetCollectionFilters()">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-datatable pt-0 m-5">
                <x-datatable id="collection-table" ajax="{{ route('console.collections.index') }}" :columns="[
                    [
                        'data' => 'DT_RowIndex',
                        'name' => 'DT_RowIndex',
                        'label' => '#',
                        'orderable' => false,
                        'searchable' => false,
                    ],
                    ['data' => 'product_name', 'name' => 'productSetting.name', 'label' => 'Product'],
                    ['data' => 'quantity', 'name' => 'quantity', 'label' => 'Quantity'],
                    ['data' => 'material', 'name' => 'material', 'label' => 'Material'],
                    ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging'],
                    ['data' => 'name', 'name' => 'name', 'label' => 'Customer Name'],
                    ['data' => 'email', 'name' => 'email', 'label' => 'Email'],
                    ['data' => 'phone', 'name' => 'phone', 'label' => 'Phone'],
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
    @include('console.collections.partials.add-or-edit-collection-modal')
@endpush

@push('scripts')
    <script>
        function setProductSelectHoverTitle(selectEl) {
            if (!selectEl) return;

            const selectedOption = selectEl.options[selectEl.selectedIndex];
            if (!selectedOption) {
                selectEl.removeAttribute('title');
                return;
            }

            selectEl.title = selectedOption.getAttribute('data-full-name') || selectedOption.text || '';
        }

        function setDataAddOrEditCollectionModal(element) {
            const modalElement = document.getElementById('addOrEditCollectionModal');
            const form = modalElement.querySelector('form');
            const modalTitle = document.getElementById('collectionModalTitle');
            const productSettingSelect = form.querySelector('#product_setting_id');

            if (element) {
                if (modalTitle) {
                    modalTitle.textContent = 'Edit Collection';
                }
                const collectionId = element.getAttribute('data-id');
                fetch(`{{ route('console.collections.show') }}/?id=${collectionId}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const collection = result.data;
                            form.querySelector('#product_setting_id').value = collection.product_setting_id || '';
                            setProductSelectHoverTitle(productSettingSelect);
                            form.querySelector('#quantity').value = collection.quantity ?? 0;
                            form.querySelector('#material').value = collection.material || 'Carpet';
                            form.querySelector('#edging').value = collection.edging || '';
                            form.querySelector('#name').value = collection.name || '';
                            form.querySelector('#email').value = collection.email || '';
                            form.querySelector('#phone').value = collection.phone || '';
                            form.setAttribute('data-id', collection.id);
                            new bootstrap.Modal(modalElement).show();
                        } else {
                            toastr.error(result.message || 'Failed to load collection.');
                        }
                    })
                    .catch(() => {
                        toastr.error('Failed to load collection data.');
                    });
            } else {
                if (modalTitle) {
                    modalTitle.textContent = 'Add Collection';
                }
                form.reset();
                form.removeAttribute('data-id');
                form.querySelector('#material').value = 'Carpet';
                form.querySelector('#quantity').value = 0;
                setProductSelectHoverTitle(productSettingSelect);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modalElement = document.getElementById('addOrEditCollectionModal');
            const form = modalElement.querySelector('form');
            const fromDateFilterInput = document.getElementById('from_date_filter');
            const toDateFilterInput = document.getElementById('to_date_filter');
            const productFilterSelect = document.getElementById('product_filter');
            const productSettingSelect = document.getElementById('product_setting_id');

            setProductSelectHoverTitle(productFilterSelect);
            setProductSelectHoverTitle(productSettingSelect);

            if (productFilterSelect) {
                productFilterSelect.addEventListener('change', function() {
                    setProductSelectHoverTitle(productFilterSelect);
                });
            }

            if (productSettingSelect) {
                productSettingSelect.addEventListener('change', function() {
                    setProductSelectHoverTitle(productSettingSelect);
                });
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(form);
                const isEdit = form.hasAttribute('data-id');
                let url = '{{ route('console.collections.store') }}';

                if (isEdit) {
                    url = '{{ route('console.collections.update') }}';
                    formData.append('_method', 'PUT');
                    formData.append('id', form.getAttribute('data-id'));
                }

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success!', data.message, 'success');
                            bootstrap.Modal.getInstance(modalElement).hide();
                            form.reset();
                            form.removeAttribute('data-id');
                            $('#collection-table').DataTable().ajax.reload();
                        } else {
                            toastr.error(data.message || 'Something went wrong.');
                        }
                    })
                    .catch(() => {
                        toastr.error('Something went wrong. Try again later.');
                    });
            });

            if (fromDateFilterInput) {
                fromDateFilterInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyCollectionFilters();
                    }
                });
            }
            if (toDateFilterInput) {
                toDateFilterInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyCollectionFilters();
                    }
                });
            }
        });

        function applyCollectionFilters() {
            const productSettingId = document.getElementById('product_filter').value;
            const materialType = document.getElementById('material_type_filter').value;
            const edging = document.getElementById('edging_filter').value;
            const fromDate = document.getElementById('from_date_filter').value;
            const toDate = document.getElementById('to_date_filter').value;
            let url = `{{ route('console.collections.index') }}?`;

            if (productSettingId) url += `product_setting_id=${encodeURIComponent(productSettingId)}&`;
            if (materialType) url += `material_type=${encodeURIComponent(materialType)}&`;
            if (edging) url += `edging=${encodeURIComponent(edging)}&`;
            if (fromDate) url += `from_date=${encodeURIComponent(fromDate)}&`;
            if (toDate) url += `to_date=${encodeURIComponent(toDate)}&`;

            url = url.replace(/[&?]$/, '');

            $('#collection-table').DataTable().ajax.url(url).load();
        }

        function resetCollectionFilters() {
            const productFilterSelect = document.getElementById('product_filter');

            productFilterSelect.value = '';
            document.getElementById('material_type_filter').value = '';
            document.getElementById('edging_filter').value = '';
            document.getElementById('from_date_filter').value = '';
            document.getElementById('to_date_filter').value = '';
            setProductSelectHoverTitle(productFilterSelect);
            $('#collection-table').DataTable().ajax.url('{{ route('console.collections.index') }}').load();
        }

        function deleteCollection(element) {
            const collectionId = element.getAttribute('data-id');

            requestAccessPassword({
                title: 'Delete Collection',
                onVerified(password) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'This will permanently delete the collection.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        fetch('{{ route('console.collections.destroy') }}', {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id: collectionId,
                                    password
                                })
                            })
                            .then(parseProtectedDeleteResponse)
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Deleted!', data.message, 'success');
                                    $('#collection-table').DataTable().ajax.reload();
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
