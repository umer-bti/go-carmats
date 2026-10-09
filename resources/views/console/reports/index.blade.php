@extends('console.layout.app')

@section('title', 'Evri Shipping Reports')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">Evri Shipping Label Report</h5>
                <p class="text-muted small mb-0 mt-1">
                    Compare each order line shipped in our system vs whether the parcel exists on Evri and its current status.
                </p>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Total Evri orders</div>
                            <div class="fs-4 fw-semibold">{{ $stats['total'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 border-success">
                            <div class="text-muted small">Verified</div>
                            <div class="fs-4 fw-semibold text-success">{{ $stats['verified'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100 border-warning">
                            <div class="text-muted small">Mismatch</div>
                            <div class="fs-4 fw-semibold text-warning">{{ $stats['mismatch'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Pending check</div>
                            <div class="fs-4 fw-semibold">{{ $stats['pending'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-2">
                        <label for="start_date" class="form-label fw-semibold">From</label>
                        <input type="date" id="start_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label fw-semibold">To</label>
                        <input type="date" id="end_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="order_id" class="form-label fw-semibold">Order ID</label>
                        <input type="text" id="order_id" class="form-control" placeholder="Search order id">
                    </div>
                    <div class="col-md-2">
                        <label for="system_status" class="form-label fw-semibold">System status</label>
                        <select id="system_status" class="form-select">
                            <option value="">All</option>
                            <option value="shipped">Shipped</option>
                            <option value="awaiting_shipment">Awaiting Shipment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="verification_result" class="form-label fw-semibold">Verification</label>
                        <select id="verification_result" class="form-select">
                            <option value="">All</option>
                            <option value="verified">Verified</option>
                            <option value="mismatch">Mismatch</option>
                            <option value="error">Error</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="applyReportFilters()">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearReportFilters()">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-success" id="verifyAllBtn" onclick="verifyAllVisible()">
                        <i class="fas fa-sync-alt me-1"></i> Verify all on this page
                    </button>
                </div>

                <x-datatable id="evriReportTable"
                    ajax="{{ route('console.reports.index') }}"
                    export
                    export-filename="evri_shipping_report"
                    :columns="[
                        ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'label' => '#', 'orderable' => false, 'searchable' => false],
                        ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                        ['data' => 'order_item_id', 'name' => 'order_item_id', 'label' => 'Item ID', 'orderable' => false, 'searchable' => false],
                        ['data' => 'status', 'name' => 'status', 'label' => 'System Status'],
                        ['data' => 'system_shipped_label', 'name' => 'system_shipped_label', 'label' => 'Shipped (System)', 'orderable' => false, 'searchable' => false, 'raw' => true],
                        ['data' => 'system_label_saved', 'name' => 'system_label_saved', 'label' => 'Label Saved', 'orderable' => false, 'searchable' => false, 'raw' => true],
                        ['data' => 'tracking_number', 'name' => 'tracking_number', 'label' => 'Tracking #'],
                        ['data' => 'evri_label_exists_label', 'name' => 'evri_label_exists_label', 'label' => 'Evri Parcel Exists', 'orderable' => false, 'searchable' => false, 'raw' => true],
                        ['data' => 'evri_status_label', 'name' => 'evri_status_label', 'label' => 'Evri Status', 'orderable' => false, 'searchable' => false, 'raw' => true],
                        ['data' => 'verification_badge', 'name' => 'verification_badge', 'label' => 'Match', 'orderable' => false, 'searchable' => false, 'raw' => true],
                        ['data' => 'verified_at', 'name' => 'verified_at', 'label' => 'Last Checked', 'orderable' => false, 'searchable' => false],
                        ['data' => 'action', 'name' => 'action', 'label' => 'Action', 'orderable' => false, 'searchable' => false, 'raw' => true],
                    ]" />
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function buildReportUrl() {
        let url = `{{ route('console.reports.index') }}`;
        const params = [];
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const orderId = document.getElementById('order_id').value;
        const systemStatus = document.getElementById('system_status').value;
        const verificationResult = document.getElementById('verification_result').value;

        if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
        if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
        if (orderId) params.push(`order_id=${encodeURIComponent(orderId)}`);
        if (systemStatus) params.push(`system_status=${encodeURIComponent(systemStatus)}`);
        if (verificationResult) params.push(`verification_result=${encodeURIComponent(verificationResult)}`);

        if (params.length) {
            url += `?${params.join('&')}`;
        }

        return url;
    }

    function applyReportFilters() {
        $('#evriReportTable').DataTable().ajax.url(buildReportUrl()).load();
        toastr.success('Filters applied.');
    }

    function clearReportFilters() {
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('order_id').value = '';
        document.getElementById('system_status').value = '';
        document.getElementById('verification_result').value = '';
        $('#evriReportTable').DataTable().ajax.url(`{{ route('console.reports.index') }}`).load();
        toastr.info('Filters cleared.');
    }

    async function verifyEvriOrder(orderId, button) {
        if (!orderId) return;

        const originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        try {
            const response = await fetch(`{{ route('console.reports.verify') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: orderId }),
            });

            const result = await response.json();
            if (result.success) {
                toastr.success(result.message || 'Verified.');
                $('#evriReportTable').DataTable().ajax.reload(null, false);
            } else {
                toastr.error(result.message || 'Verification failed.');
            }
        } catch (error) {
            console.error(error);
            toastr.error('Something went wrong.');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }

    async function verifyAllVisible() {
        const btn = document.getElementById('verifyAllBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';

        const orderIds = Array.from(document.querySelectorAll('#evriReportTable .verify-evri-btn'))
            .map(el => parseInt(el.dataset.id, 10))
            .filter(id => !Number.isNaN(id));

        try {
            const response = await fetch(`{{ route('console.reports.verifyBulk') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_ids: orderIds }),
            });

            const result = await response.json();
            if (result.success) {
                toastr.success(result.message || 'Bulk verification completed.');
                $('#evriReportTable').DataTable().ajax.reload(null, false);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                toastr.error(result.message || 'Bulk verification failed.');
            }
        } catch (error) {
            console.error(error);
            toastr.error('Something went wrong.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    document.addEventListener('click', function(event) {
        const btn = event.target.closest('.verify-evri-btn');
        if (!btn) return;
        verifyEvriOrder(btn.dataset.id, btn);
    });
</script>
@endpush
