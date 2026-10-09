@extends('console.layout.app')

@section('title', 'Stitchers')

@push('styles')
    <style>
        /* Keep Edit / Delete on one horizontal row (cell-fit + flex-wrap was stacking them). */
        #stitchers-table_wrapper td.stitcher-actions-col,
        #stitchers-table_wrapper th.stitcher-actions-col {
            white-space: nowrap;
            width: 1%;
        }

        #stitchers-table_wrapper .stitcher-row-actions {
            flex-wrap: nowrap !important;
        }

        /* Assign modal: disabled radios stay full contrast (no faded row/label). */
        #stitcherAssignOrderModal #stitcherAssignOrderLineRadios .form-check-input:disabled {
            opacity: 1;
            filter: none;
        }

        #stitcherAssignOrderModal #stitcherAssignOrderLineRadios .form-check-input:disabled + .form-check-label {
            opacity: 1;
            color: inherit;
        }
    </style>
@endpush

@section('content')
    @php
        $scanFilterStart = \Illuminate\Support\Carbon::now()->startOfMonth()->format('Y-m-d');
        $scanFilterEnd = \Illuminate\Support\Carbon::now()->endOfMonth()->format('Y-m-d');
    @endphp
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Stitchers</h5>
                </div>
                <div>
                    @can('create stitchers')
                        <button type="button" class="btn btn-primary stitcher-modal-open" data-bs-toggle="modal" data-bs-target="#stitcherFormModal"
                            data-mode="create">
                            <i class="icon-base ti tabler-plus me-1"></i> New stitcher
                        </button>
                    @endcan
                </div>
            </div>

            <div class="card-body">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body pt-3">
                        <div class="row g-3 align-items-end flex-wrap">
                            <div class="col-md-2 col-sm-6">
                                <label for="stitcher_scan_start" class="form-label fw-semibold mb-1">From</label>
                                <input type="date" class="form-control" id="stitcher_scan_start" name="stitcher_scan_start" value="{{ $scanFilterStart }}">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="stitcher_scan_end" class="form-label fw-semibold mb-1">To</label>
                                <input type="date" class="form-control" id="stitcher_scan_end" name="stitcher_scan_end" value="{{ $scanFilterEnd }}">
                            </div>
                            <div class="col-auto d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-primary" onclick="stitchersScanFilterApply()">
                                    <i class="fas fa-filter me-1"></i> Apply
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="stitchersScanFilterResetMonth()">
                                    <i class="fas fa-times me-1"></i> Reset to this month
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <div class="card-datatable pt-0 m-5">
                <x-datatable id="stitchers-table" ajax="{{ route('console.stitchers.index') }}"
                    :filters="['stitcher_scan_start', 'stitcher_scan_end']"
                    :columns="[
                    [
                        'data' => 'DT_RowIndex',
                        'name' => 'DT_RowIndex',
                        'label' => '#',
                        'orderable' => false,
                        'searchable' => false,
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'label' => 'Name',
                        'default_order' => 'asc',
                    ],
                    [
                        'data' => 'orders_count',
                        'name' => 'orders_count',
                        'label' => 'Assigned orders',
                        'searchable' => false,
                    ],
                    [
                        'data' => 'action',
                        'name' => 'action',
                        'label' => 'Action',
                        'raw' => true,
                        'orderable' => false,
                        'searchable' => false,
                        'class' => 'stitcher-actions-col',
                    ],
                ]" />
            </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function stitchersScanFilterApply() {
                var $ = window.jQuery;
                if ($ && $.fn.dataTable && $.fn.dataTable.isDataTable('#stitchers-table')) {
                    $('#stitchers-table').DataTable().ajax.reload(null, false);
                    return;
                }
                if (typeof DataTable !== 'undefined' && typeof DataTable.get === 'function') {
                    var api = DataTable.get(document.getElementById('stitchers-table'));
                    if (api && api.ajax && typeof api.ajax.reload === 'function') {
                        api.ajax.reload();
                    }
                }
            }

            function stitchersScanFilterResetMonth() {
                var now = new Date();
                var y = now.getFullYear();
                var m = String(now.getMonth() + 1).padStart(2, '0');
                var first = y + '-' + m + '-01';
                var lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
                var last = y + '-' + m + '-' + String(lastDay).padStart(2, '0');
                document.getElementById('stitcher_scan_start').value = first;
                document.getElementById('stitcher_scan_end').value = last;
                stitchersScanFilterApply();
            }
        </script>
    @endpush

    @canany(['create stitchers', 'edit stitchers'])
        <div class="modal fade" id="stitcherFormModal" tabindex="-1" aria-labelledby="stitcherFormModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stitcherFormModalLabel">New stitcher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="stitcherForm">
                            <input type="hidden" id="stitcherId" name="id" value="">
                            <div class="mb-0">
                                <label for="stitcherName" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="stitcherName" name="name" maxlength="255" required
                                    placeholder="Stitcher name" autocomplete="off">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="stitcherFormSubmit">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endcanany

    @can('edit stitchers')
        <div class="modal fade" id="stitcherAssignOrderModal" tabindex="-1" aria-labelledby="stitcherAssignOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stitcherAssignOrderModalLabel">Assign/Reassign Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2" id="stitcherAssignOrderHint">Scan or type the order ID, then press Enter or Assign.</p>
                        <form id="stitcherAssignOrderForm">
                            <input type="hidden" id="stitcherAssignStitcherId" value="">
                            <div class="mb-0">
                                <label for="stitcherAssignOrderInput" class="form-label">Order ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="stitcherAssignOrderInput" name="order_id"
                                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text"
                                    placeholder="Order ID (barcode or manual)">
                            </div>
                        </form>
                        <div id="stitcherAssignOrderLinePicker" class="d-none mt-3">
                            <label class="form-label fw-semibold">Select item to assign</label>
                            <p class="text-muted small mb-2">This order has more than one item. Choose which item to link to this stitcher.</p>
                            <div id="stitcherAssignOrderLineRadios" class="list-group list-group-flush border rounded"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="stitcherAssignOrderSubmit">Assign</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@canany(['create stitchers', 'edit stitchers'])
    @push('scripts')
        <script>
            (function() {
                const modalEl = document.getElementById('stitcherFormModal');
                if (!modalEl) return;

                const titleEl = document.getElementById('stitcherFormModalLabel');
                const idInput = document.getElementById('stitcherId');
                const nameInput = document.getElementById('stitcherName');
                const submitBtn = document.getElementById('stitcherFormSubmit');
                const baseUrl = @json(url('console/stitchers'));
                const csrf = @json(csrf_token());

                function prepareStitcherModal(trigger) {
                    if (!trigger) {
                        return;
                    }
                    const mode = trigger.getAttribute('data-mode');
                    if (mode === 'create') {
                        titleEl.textContent = 'New stitcher';
                        idInput.value = '';
                        nameInput.value = '';
                    } else if (mode === 'edit') {
                        titleEl.textContent = 'Edit stitcher';
                        idInput.value = trigger.getAttribute('data-id') || '';
                        nameInput.value = trigger.getAttribute('data-name') || '';
                    }
                }

                // DataTables injects Edit buttons after load; Bootstrap often leaves relatedTarget null — capture phase fixes id/name before open.
                document.body.addEventListener('click', function(event) {
                    const trigger = event.target.closest('.stitcher-modal-open[data-bs-target="#stitcherFormModal"]');
                    if (!trigger) {
                        return;
                    }
                    prepareStitcherModal(trigger);
                }, true);

                modalEl.addEventListener('show.bs.modal', function(event) {
                    if (event.relatedTarget) {
                        prepareStitcherModal(event.relatedTarget);
                    }
                });

                const formEl = document.getElementById('stitcherForm');

                function submitStitcherForm() {
                    const id = idInput.value.trim();
                    const name = nameInput.value.trim();
                    if (!name) {
                        toastr.error('Please enter a name.');
                        return;
                    }

                    const isEdit = id.length > 0;
                    const url = isEdit ? baseUrl + '/' + id : baseUrl;
                    const method = isEdit ? 'PUT' : 'POST';

                    submitBtn.disabled = true;
                    fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                name
                            }),
                        })
                        .then(function(r) {
                            return r.json().then(function(data) {
                                return {
                                    ok: r.ok,
                                    data
                                };
                            });
                        })
                        .then(function(result) {
                            if (result.ok && result.data.success) {
                                toastr.success(result.data.message);
                                bootstrap.Modal.getInstance(modalEl).hide();
                                window.location.reload();
                            } else if (result.data.errors && result.data.errors.name) {
                                toastr.error(result.data.errors.name[0]);
                            } else {
                                toastr.error(result.data.message || 'Something went wrong.');
                            }
                        })
                        .catch(function() {
                            toastr.error('Request failed.');
                        })
                        .finally(function() {
                            submitBtn.disabled = false;
                        });
                }

                submitBtn.addEventListener('click', submitStitcherForm);

                // Enter in the name field must not submit the form natively (that reloads the page); use the same AJAX save as the button.
                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitStitcherForm();
                });
            })();
        </script>
    @endpush
@endcanany

@can('edit stitchers')
    @push('scripts')
        <script>
            (function() {
                const assignModalEl = document.getElementById('stitcherAssignOrderModal');
                if (!assignModalEl) return;

                const titleEl = document.getElementById('stitcherAssignOrderModalLabel');
                const hintEl = document.getElementById('stitcherAssignOrderHint');
                const stitcherIdHidden = document.getElementById('stitcherAssignStitcherId');
                const orderInput = document.getElementById('stitcherAssignOrderInput');
                const assignSubmitBtn = document.getElementById('stitcherAssignOrderSubmit');
                const assignForm = document.getElementById('stitcherAssignOrderForm');
                const linePickerEl = document.getElementById('stitcherAssignOrderLinePicker');
                const lineRadiosEl = document.getElementById('stitcherAssignOrderLineRadios');
                const baseUrl = @json(url('console/stitchers'));
                const csrf = @json(csrf_token());

                function escapeHtml(s) {
                    if (s == null || s === '') return '';
                    const d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                function resetLinePicker() {
                    if (linePickerEl) linePickerEl.classList.add('d-none');
                    if (lineRadiosEl) lineRadiosEl.innerHTML = '';
                    if (assignSubmitBtn) {
                        assignSubmitBtn.textContent = 'Assign';
                        assignSubmitBtn.disabled = false;
                    }
                }

                function updateAssignSubmitForPickerState() {
                    if (!assignSubmitBtn) return;
                    if (!linePickerEl || linePickerEl.classList.contains('d-none')) {
                        assignSubmitBtn.disabled = false;
                        return;
                    }
                    var enabled = lineRadiosEl.querySelectorAll('input[name="stitcher_assign_line_id"]:not(:disabled)');
                    assignSubmitBtn.disabled = enabled.length === 0;
                }

                function prepareAssignModal(trigger) {
                    if (!trigger) return;
                    const id = trigger.getAttribute('data-id') || '';
                    const name = trigger.getAttribute('data-name') || '';
                    stitcherIdHidden.value = id;
                    titleEl.textContent = name ? ('Assign/Reassign Order — ' + name) : 'Assign/Reassign Order';
                    if (hintEl) {
                        hintEl.textContent = name
                            ? ('Items for "' + name + '" will get this stitcher and scan time. Scan or type the order ID.')
                            : 'Scan or type the order ID, then press Enter or Assign.';
                    }
                    orderInput.value = '';
                    resetLinePicker();
                }

                document.body.addEventListener('click', function(event) {
                    const trigger = event.target.closest('.stitcher-assign-open[data-bs-target="#stitcherAssignOrderModal"]');
                    if (!trigger) return;
                    prepareAssignModal(trigger);
                }, true);

                assignModalEl.addEventListener('show.bs.modal', function(event) {
                    if (event.relatedTarget) {
                        prepareAssignModal(event.relatedTarget);
                    }
                });

                assignModalEl.addEventListener('shown.bs.modal', function() {
                    orderInput.focus();
                    orderInput.select();
                });

                assignModalEl.addEventListener('hidden.bs.modal', function() {
                    resetLinePicker();
                });

                orderInput.addEventListener('input', function() {
                    if (linePickerEl && !linePickerEl.classList.contains('d-none')) {
                        resetLinePicker();
                    }
                });

                function showLinePicker(lines) {
                    if (!lineRadiosEl || !linePickerEl) return;
                    var defaultIdx = lines.findIndex(function(l) {
                        return !l.already_assigned_here;
                    });
                    var hasAssignable = defaultIdx >= 0;
                    lineRadiosEl.innerHTML = lines.map(function(line, idx) {
                        const rid = 'stitcher_assign_line_' + line.id;
                        const disabled = !!line.already_assigned_here;
                        const badge = disabled
                            ? ' <span class="badge bg-label-warning text-warning fw-semibold ms-1">Already assigned to the selected stitcher</span>'
                            : '';
                        const disabledAttr = disabled ? ' disabled' : '';
                        const checkedAttr = (hasAssignable && idx === defaultIdx) ? ' checked' : '';
                        return '<div class="list-group-item p-2">' +
                            '<div class="form-check m-0">' +
                            '<input class="form-check-input" type="radio" name="stitcher_assign_line_id" id="' + rid + '" value="' + line.id + '"' + checkedAttr + disabledAttr + '>' +
                            '<label class="form-check-label w-100 small" for="' + rid + '">' + escapeHtml(line.label) + badge + '</label>' +
                            '</div></div>';
                    }).join('');
                    linePickerEl.classList.remove('d-none');
                    if (assignSubmitBtn) assignSubmitBtn.textContent = 'Assign selected item';
                    updateAssignSubmitForPickerState();
                }

                function getSelectedLineId() {
                    if (!lineRadiosEl) return null;
                    const r = lineRadiosEl.querySelector('input[name="stitcher_assign_line_id"]:checked:not(:disabled)');
                    return r ? parseInt(r.value, 10) : null;
                }

                function reloadStitchersTable() {
                    var $ = window.jQuery;
                    if ($ && $.fn.dataTable && $.fn.dataTable.isDataTable('#stitchers-table')) {
                        $('#stitchers-table').DataTable().ajax.reload(null, false);
                        return;
                    }
                    if (typeof DataTable !== 'undefined' && typeof DataTable.get === 'function') {
                        var api = DataTable.get(document.getElementById('stitchers-table'));
                        if (api && api.ajax && typeof api.ajax.reload === 'function') {
                            api.ajax.reload(null, false);
                        }
                    }
                }

                function submitAssignOrder() {
                    const stitcherId = stitcherIdHidden.value.trim();
                    const orderId = orderInput.value.trim();
                    if (!stitcherId) {
                        toastr.error('Missing stitcher.');
                        return;
                    }
                    if (!orderId) {
                        toastr.error('Please enter or scan an order ID.');
                        orderInput.focus();
                        return;
                    }

                    const payload = {
                        order_id: orderId
                    };
                    if (linePickerEl && !linePickerEl.classList.contains('d-none')) {
                        var assignableN = lineRadiosEl.querySelectorAll('input[name="stitcher_assign_line_id"]:not(:disabled)').length;
                        if (assignableN === 0) {
                            toastr.warning('All items are already assigned to the selected stitcher.');
                            return;
                        }
                        const lid = getSelectedLineId();
                        if (!lid) {
                            toastr.error('Please select an item.');
                            return;
                        }
                        payload.line_ids = [lid];
                    }

                    assignSubmitBtn.disabled = true;
                    fetch(baseUrl + '/' + encodeURIComponent(stitcherId) + '/assign-order', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        })
                        .then(function(r) {
                            return r.json().then(function(data) {
                                return {
                                    ok: r.ok,
                                    status: r.status,
                                    data
                                };
                            });
                        })
                        .then(function(result) {
                            if (result.ok && result.data.success) {
                                if (result.data.requires_selection && result.data.lines && result.data.lines.length) {
                                    showLinePicker(result.data.lines);
                                    toastr.info(result.data.message || 'Select an item, then press Assign again.');
                                    return;
                                }
                                if (result.data.noop) {
                                    toastr.warning(result.data.message || 'No change.');
                                    var instNoop = bootstrap.Modal.getInstance(assignModalEl);
                                    if (instNoop) instNoop.hide();
                                    return;
                                }
                                toastr.success(result.data.message || 'Order assigned successfully.');
                                var inst = bootstrap.Modal.getInstance(assignModalEl);
                                if (inst) inst.hide();
                                reloadStitchersTable();
                            } else if (result.data && result.data.errors && result.data.errors.order_id) {
                                toastr.error(result.data.errors.order_id[0]);
                            } else if (result.data && result.data.message) {
                                toastr.error(result.data.message);
                            } else {
                                toastr.error('Could not assign order.');
                            }
                        })
                        .catch(function() {
                            toastr.error('Request failed.');
                        })
                        .finally(function() {
                            updateAssignSubmitForPickerState();
                        });
                }

                if (linePickerEl) {
                    linePickerEl.addEventListener('change', function(e) {
                        if (e.target && e.target.name === 'stitcher_assign_line_id') {
                            updateAssignSubmitForPickerState();
                        }
                    });
                }

                assignSubmitBtn.addEventListener('click', submitAssignOrder);
                assignForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitAssignOrder();
                });
            })();
        </script>
    @endpush
@endcan

@can('delete stitchers')
    @push('scripts')
        <script>
            (function() {
                const baseUrl = @json(url('console/stitchers'));
                const stitchersVerifyUrl = @json(route('console.stitchers.verify'));
                const csrf = @json(csrf_token());

                function reloadStitchersTable() {
                    var $ = window.jQuery;
                    if ($ && $.fn.dataTable && $.fn.dataTable.isDataTable('#stitchers-table')) {
                        $('#stitchers-table').DataTable().ajax.reload(null, false);
                        return;
                    }
                    if (typeof DataTable !== 'undefined' && typeof DataTable.get === 'function') {
                        var api = DataTable.get(document.getElementById('stitchers-table'));
                        if (api && api.ajax && typeof api.ajax.reload === 'function') {
                            api.ajax.reload(null, false);
                        }
                    }
                }

                function requestWithStitchersAccess(url, options, onSuccess, onError) {
                    fetch(url, options)
                        .then(function(r) {
                            return r.json().then(function(data) {
                                return {
                                    ok: r.ok,
                                    status: r.status,
                                    data: data || {},
                                };
                            }).catch(function() {
                                return {
                                    ok: r.ok,
                                    status: r.status,
                                    data: {},
                                };
                            });
                        })
                        .then(function(res) {
                            if (res.status === 403 && res.data && res.data.requires_stitchers_access) {
                                const modalEl = document.getElementById('settingsAccessModal');
                                if (!modalEl || typeof bootstrap === 'undefined') {
                                    onError({
                                        message: res.data.message || 'Stitchers access is required.'
                                    });
                                    return;
                                }

                                window.__passwordGateVerifyUrl = stitchersVerifyUrl;
                                window.__passwordGateRedirectUrl = null;
                                window.__settingsAccessModalCustomCopy = {
                                    title: 'Access Stitchers',
                                    hint: 'Enter password to continue this Stitchers action.',
                                };
                                window.__pendingPasswordGateCallback = function() {
                                    requestWithStitchersAccess(url, options, onSuccess, onError);
                                };

                                const modal = new bootstrap.Modal(modalEl);
                                modal.show();
                                return;
                            }

                            if (res.ok && res.data.success) {
                                onSuccess(res.data);
                                return;
                            }

                            onError(res.data || {});
                        })
                        .catch(function() {
                            onError({
                                message: 'Request failed.'
                            });
                        });
                }

                document.body.addEventListener('click', function(e) {
                    const unassignBtn = e.target.closest('.stitcher-unassign-btn');
                    if (unassignBtn) {
                        const id = unassignBtn.getAttribute('data-id');
                        const name = unassignBtn.getAttribute('data-name') || 'this stitcher';

                        Swal.fire({
                            title: 'Unassign all orders?',
                            text: 'All orders linked to "' + name + '" will have their stitcher cleared.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, reset',
                            cancelButtonText: 'Cancel',
                            reverseButtons: true
                        }).then(function(result) {
                            if (!result.isConfirmed) {
                                return;
                            }
                            requestWithStitchersAccess(
                                baseUrl + '/' + encodeURIComponent(id) + '/unassign-orders',
                                {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({}),
                                },
                                function(data) {
                                    toastr.success(data.message);
                                    reloadStitchersTable();
                                },
                                function(data) {
                                    Swal.fire('Error', data.message || 'Could not unassign orders.', 'error');
                                }
                            );
                        });
                        return;
                    }

                    const btn = e.target.closest('.stitcher-delete-btn');
                    if (!btn) {
                        return;
                    }

                    const id = btn.getAttribute('data-id');
                    const name = btn.getAttribute('data-name') || 'this stitcher';

                    requestAccessPassword({
                        title: 'Delete Stitcher',
                        onVerified(password) {
                            Swal.fire({
                                title: 'Are you sure?',
                                text: 'Remove "' + name + '" permanently?',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, delete it!',
                                cancelButtonText: 'Cancel',
                                reverseButtons: true
                            }).then(function(result) {
                                if (!result.isConfirmed) {
                                    return;
                                }

                                fetch(baseUrl + '/' + encodeURIComponent(id), {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({ password }),
                                })
                                .then(parseProtectedDeleteResponse)
                                .then(function(data) {
                                    Swal.fire('Deleted!', data.message, 'success').then(function() {
                                        window.location.reload();
                                    });
                                })
                                .catch(function(error) {
                                    showProtectedDeleteError(error, 'Could not delete.');
                                });
                            });
                        }
                    });
                });
            })();
        </script>
    @endpush
@endcan
