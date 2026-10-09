@extends('console.layout.app')

@section('title', 'Tracking')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none">
                <div>
                    <h5 class="card-title mb-0">Tracking</h5>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrackingModal">
                        <i class="fas fa-plus me-1"></i> Add Tracking
                    </button>
                </div>
            </div>

            <div class="card-body mt-5">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <label for="start_date" class="form-label mb-0">From:</label>
                                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" style="width: 150px;">
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label for="end_date" class="form-label mb-0">To:</label>
                                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" style="width: 150px;">
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label for="order_id" class="form-label mb-0">Order ID:</label>
                                <input type="text" class="form-control form-control-sm" id="order_id" name="order_id" placeholder="Search order id" style="width: 200px;">
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label for="tracking_number" class="form-label mb-0">Tracking #:</label>
                                <input type="text" class="form-control form-control-sm" id="tracking_number" name="tracking_number" placeholder="Search tracking" style="width: 220px;">
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="applyDateFilter()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearDateFilter()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                            
                        </div>
                    </div>
                </div>

                <x-datatable id="trackingTable" ajax="{{ route('console.tracking.index') }}" export export-filename="tracking"
                    :columns="[
                        ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'label' => '#', 'orderable' => false, 'searchable' => false],
                        ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order ID'],
                        ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type'],
                        ['data' => 'make_model', 'name' => 'make_model', 'label' => 'Make & Model'],
                        ['data' => 'quantity', 'name' => 'quantity', 'label' => 'Quantity'],
                        ['data' => 'recipient_name', 'name' => 'recipient_name', 'label' => 'Recipient'],
                        ['data' => 'address', 'name' => 'address', 'label' => 'Address'],
                        ['data' => 'city', 'name' => 'city', 'label' => 'City'],
                        ['data' => 'postal_code', 'name' => 'postal_code', 'label' => 'Postcode'],
                        ['data' => 'date', 'name' => 'date', 'label' => 'Order Date', 'default_order' => 'desc'],
                        ['data' => 'tracking_number', 'name' => 'tracking_number', 'label' => 'Tracking Number'],
                    ]" />
            </div>
        </div>
    </div>
@endsection

@push('partials')
    @include('console.tracking.partials.add-tracking-modal')
@endpush

@push('scripts')
<script src="{{ asset('themes/console/assets/vendor/libs/select2/select2.js') }}"></script>
<script>
function applyDateFilter() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const orderId = document.getElementById('order_id').value;
    const trackingNumber = document.getElementById('tracking_number').value;

    let filterUrl = `{{ route('console.tracking.index') }}`;
    const params = [];
    if (startDate) params.push(`start_date=${startDate}`);
    if (endDate) params.push(`end_date=${endDate}`);
    if (orderId) params.push(`order_id=${encodeURIComponent(orderId)}`);
    if (trackingNumber) params.push(`tracking_number=${encodeURIComponent(trackingNumber)}`);
    if (params.length) filterUrl += `?${params.join('&')}`;

    $('#trackingTable').DataTable().ajax.url(filterUrl).load();
}

function clearDateFilter() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('order_id').value = '';
    document.getElementById('tracking_number').value = '';
    $('#trackingTable').DataTable().ajax.url(`{{ route('console.tracking.index') }}`).load();
}


</script>
@endpush


