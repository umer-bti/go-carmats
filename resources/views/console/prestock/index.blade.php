@extends('console.layout.app')

@section('title', 'Prestock')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div
                class="card-header border-bottom d-flex flex-nowrap justify-content-between align-items-center border-none gap-2">
                <div>
                    <h5 class="card-title mb-0">Prestock</h5>
                </div>
                @can('create prestock')
                    @if ($products->isEmpty())
                        <span class="text-muted small">Add products first before creating prestock.</span>
                    @else
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPrestockModal"
                            onclick="resetCreatePrestockModal()">
                            Add prestock
                        </button>
                    @endif
                @endcan
            </div>
            <div class="card-datatable pt-0 m-5">
                <x-datatable id="prestock-table" ajax="{{ route('console.prestock.index') }}" :columns="[
                    [
                        'data' => 'DT_RowIndex',
                        'name' => 'DT_RowIndex',
                        'label' => '#',
                        'orderable' => false,
                        'searchable' => false,
                    ],
                    ['data' => 'product_name', 'name' => 'product_name', 'label' => 'Product'],
                    ['data' => 'stock', 'name' => 'stock', 'label' => 'Remaining stock'],
                    ['data' => 'material', 'name' => 'material', 'label' => 'Material'],
                    [
                        'data' => 'comment',
                        'name' => 'comment',
                        'label' => 'Comment',
                        'raw' => true,
                    ],
                    [
                        'data' => 'action',
                        'name' => 'action',
                        'label' => 'Action',
                        'orderable' => false,
                        'searchable' => false,
                        'raw' => true,
                        'class' => 'cell-fit',
                    ],
                ]" />
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.prestock.partials.modals')
    @include('console.prestock.partials.print_label_modal')
@endpush

@push('scripts')
    <script src="{{ asset('themes/console/assets/vendor/libs/select2/select2.js') }}"></script>
    <script>
        const prestockBaseUrl = @json(rtrim(url('/console/prestock'), '/'));
        const prestockRoutes = {
            store: @json(route('console.prestock.store')),
        };
        const csrfToken = @json(csrf_token());

        function resetCreatePrestockModal() {
            const form = document.getElementById('createPrestockForm');
            if (form) {
                form.reset();
                document.getElementById('create-prestock-stock').value = '0';
                const mat = document.getElementById('create-prestock-material');
                if (mat) mat.value = 'Carpet';
            }
            if (window.jQuery) {
                jQuery('#create-prestock-product').val(null).trigger('change');
            }
        }

        document.getElementById('createPrestockForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const productId = document.getElementById('create-prestock-product')?.value;
            const stock = parseInt(document.getElementById('create-prestock-stock')?.value, 10);
            const material = document.getElementById('create-prestock-material')?.value;
            const comment = document.getElementById('create-prestock-comment')?.value?.trim() || '';

            if (!productId) {
                toastr.error('Please select a product.');
                return;
            }
            if (!material || (material !== 'Carpet' && material !== 'Rubber')) {
                toastr.error('Please select a material.');
                return;
            }
            if (Number.isNaN(stock) || stock < 0) {
                toastr.error('Remaining stock must be a valid number (0 or greater).');
                return;
            }

            const body = {
                product_setting_id: parseInt(productId, 10),
                stock,
                material,
                comment: comment || null,
            };

            fetch(prestockRoutes.store, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(body),
                })
                .then(r => r.json().then(data => ({
                    ok: r.ok,
                    data
                })))
                .then(({
                    ok,
                    data
                }) => {
                    if (ok && data.success) {
                        toastr.success(data.message || 'Saved.');
                        bootstrap.Modal.getInstance(document.getElementById('createPrestockModal'))?.hide();
                        resetCreatePrestockModal();
                        if (window.jQuery) {
                            jQuery('#prestock-table').DataTable().ajax.reload(null, false);
                        }

                        if (data.data?.id) {
                            openPrintLabelModal(data.data.id);
                        }
                    } else {
                        const msg = data.message ||
                            (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                            'Could not save.';
                        toastr.error(msg);
                    }
                })
                .catch(() => toastr.error('Something went wrong.'));
        });

        function openEditPrestockModal(el) {
            const id = el.getAttribute('data-id');
            fetch(prestockBaseUrl + '/' + id, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.data) {
                        toastr.error('Could not load prestock.');
                        return;
                    }
                    const d = res.data;
                    document.getElementById('edit-prestock-id').value = d.id;
                    const $product = jQuery('#edit-prestock-product');
                    $product.val(String(d.product_setting_id)).trigger('change');
                    document.getElementById('edit-prestock-stock').value = d.stock;
                    document.getElementById('edit-prestock-material').value = d.material || 'Carpet';
                    document.getElementById('edit-prestock-comment').value = d.comment || '';
                    new bootstrap.Modal(document.getElementById('editPrestockModal')).show();
                })
                .catch(() => toastr.error('Could not load prestock.'));
        }

        document.getElementById('editPrestockForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const id = document.getElementById('edit-prestock-id').value;
            const body = {
                product_setting_id: parseInt(document.getElementById('edit-prestock-product').value, 10),
                stock: parseInt(document.getElementById('edit-prestock-stock').value, 10),
                material: document.getElementById('edit-prestock-material').value,
                comment: document.getElementById('edit-prestock-comment').value.trim() || null,
            };

            fetch(prestockBaseUrl + '/' + id, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(body),
                })
                .then(r => r.json().then(data => ({
                    ok: r.ok,
                    data
                })))
                .then(({
                    ok,
                    data
                }) => {
                    if (ok && data.success) {
                        toastr.success(data.message || 'Updated.');
                        bootstrap.Modal.getInstance(document.getElementById('editPrestockModal'))?.hide();
                        if (window.jQuery) {
                            jQuery('#prestock-table').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        const msg = data.message ||
                            (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                            'Could not update.';
                        toastr.error(msg);
                    }
                })
                .catch(() => toastr.error('Something went wrong.'));
        });

        function confirmDeletePrestock(el) {
            const id = el.getAttribute('data-id');

            requestAccessPassword({
                title: 'Delete Prestock',
                onVerified(password) {
                    Swal.fire({
                        title: 'Delete this prestock line?',
                        text: 'This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true,
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        fetch(prestockBaseUrl + '/' + id, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ password }),
                            })
                            .then(r => r.json().then(data => ({
                                ok: r.ok,
                                data
                            })))
                            .then(({
                                ok,
                                data
                            }) => {
                                if (ok && data.success) {
                                    toastr.success(data.message || 'Deleted.');
                                    if (window.jQuery) {
                                        jQuery('#prestock-table').DataTable().ajax.reload(null, false);
                                    }
                                } else {
                                    toastr.error(data.message || 'Could not delete.');
                                }
                            })
                            .catch(() => toastr.error('Something went wrong.'));
                    });
                }
            });
        }


        function openPrintLabelModal(id) {

            const container = document.getElementById('labelContainer');

            // ✅ Show loader
            container.innerHTML = `
                <div class="d-flex justify-content-center align-items-center w-100 h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2"></div>
                        <p class="mb-0">Loading label...</p>
                    </div>
                </div>
            `;

            // ✅ Open modal (reuse instance if already created)
            let modalEl = document.getElementById('printLabelsModal');
            let modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();

            // ✅ Use Laravel route (safer than hardcoded URL)
            const url = `{{ route('console.prestock.print', ':id') }}`.replace(':id', id);

            fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                })
                .then(async (r) => {
                    let data;

                    try {
                        data = await r.json();
                    } catch {
                        throw new Error('Invalid JSON response');
                    }

                    return {
                        ok: r.ok,
                        data
                    };
                })
                .then(({
                    ok,
                    data
                }) => {

                    if (!ok || !data) {
                        toastr.error(data?.message || 'Failed to load label.');
                        container.innerHTML = `<p class="text-danger">Failed to load label.</p>`;
                        return;
                    }

                    // ✅ Render label
                    renderLabel(data);

                })
                .catch((err) => {
                    console.error(err);
                    toastr.error('Something went wrong.');
                    container.innerHTML = `<p class="text-danger">Something went wrong.</p>`;
                });
        }

        function renderLabel(data) {

            const container = document.getElementById('labelContainer');

            container.innerHTML = `
                <div class="label-card-container active" id="printArea">

                    <div class="card" style="max-width: 90vw; width: 100%;">

                        <div class="card-body text-center" style="padding: 2rem;">

                            <div class="label-details">
                                <p><strong>Product:</strong> ${data.product_name}</p>
                                <p><strong>Material Type:</strong> ${data.material}</p>
                                <p><strong>Stock:</strong> ${data.stock}</p>
                                <p><strong>Comment:</strong> ${data.comment || '-'}</p>
                            </div>

                            <div class="mt-3">
                                <img src="${data.image}" class="img-fluid" style="max-height: 350px;">
                            </div>

                        </div>
                    </div>
                </div>
            `;
        }

        $(document).on('click', '.print-single-label', function () {

            var printWindow = window.open('', '_blank');

            // ✅ ONLY target ONE clean block
            var label = document.getElementById('printArea');

            var barcodeHTML = $(label).find('.barcode').html();
            var detailsHTML = $(label).find('.label-details').html();
            var imageSrc = $(label).find('img.img-fluid').attr('src');

            var html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print Label</title>

                    <style>
                        @page { margin: 0; }

                        body {
                            margin: 0;
                            padding: 5mm;
                            font-family: Arial;
                        }

                        .box {
                            border: 1px solid #000;
                            padding: 5mm;
                            text-align: center;
                        }

                        .barcode img {
                            max-width: 100%;
                        }

                        .details p {
                            margin: 2mm 0;
                        }

                        .product-img img {
                            max-width: 100%;
                            max-height: 250px;
                            object-fit: contain;
                        }
                        .barcode p {
                            margin: 0;
                            padding: 0;
                            line-height: 1;
                        }    
                    </style>
                </head>

                <body>
                    <div class="box">

                        <div class="details">
                            ${detailsHTML}
                        </div>

                        <div class="product-img">
                            <img src="${imageSrc}">
                        </div>

                    </div>
                </body>
                </html>
            `;

            printWindow.document.write(html);
            printWindow.document.close();

            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };
        });

        jQuery(function() {
            jQuery('#create-prestock-product').select2({
                dropdownParent: jQuery('#createPrestockModal'),
                width: '100%',
                placeholder: 'Select product',
            });
            jQuery('#edit-prestock-product').select2({
                dropdownParent: jQuery('#editPrestockModal'),
                width: '100%',
                placeholder: 'Select product',
            });
        });
    </script>
@endpush
