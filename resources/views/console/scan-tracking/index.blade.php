@extends('console.layout.app')

@section('title', 'Parcels')

@push('styles')
    <style>
        .scan-tracking-overview {
            border: 1px solid rgba(13, 110, 253, 0.2);
            background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
            border-radius: 0.75rem;
            box-shadow: rgba(100, 100, 111, 0.12) 0px 7px 29px 0px;
        }

        .scan-tracking-date-form .form-control {
            max-width: 11rem;
        }

        .parcel-stat-card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: rgba(100, 100, 111, 0.08) 0px 4px 18px 0px;
            height: 100%;
        }

        .parcel-stat-card .parcel-stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 600;
        }

        .parcel-stat-card .parcel-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            color: #212529;
        }
    </style>
@endpush

@section('content')
    <div class="container mt-5">
        <div class="scan-tracking-overview rounded-3 p-4 mb-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 44px; height: 44px; font-size: 1.1rem; font-weight: 600;"
                         aria-hidden="true">
                        <i class="icon-base ti tabler-scan"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">Parcels</h4>
                        <p class="text-muted small mb-0" id="scanTrackingPeriodLabel">{{ $periodLabel }}</p>
                    </div>
                </div>

                <div class="scan-tracking-date-form d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <label for="from_date" class="form-label fw-semibold small mb-1">From</label>
                        <input type="date"
                               id="from_date"
                               class="form-control"
                               value="{{ $fromDate?->format('Y-m-d') }}"
                               aria-label="From date">
                    </div>
                    <div>
                        <label for="to_date" class="form-label fw-semibold small mb-1">To</label>
                        <input type="date"
                               id="to_date"
                               class="form-control"
                               value="{{ $toDate?->format('Y-m-d') }}"
                               aria-label="To date">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="applyScanTrackingFilters()">
                        Apply
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetScanTrackingFilters()">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="parcel-stat-card p-3">
                    <div class="parcel-stat-label mb-1">Evri Standard</div>
                    <div class="parcel-stat-value" id="parcelCountEvri">{{ number_format($providerTotals['evri']) }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="parcel-stat-card p-3">
                    <div class="parcel-stat-label mb-1">Prime</div>
                    <div class="parcel-stat-value" id="parcelCountVeeqo">{{ number_format($providerTotals['veeqo']) }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="parcel-stat-card p-3">
                    <div class="parcel-stat-label mb-1">Parcelforce</div>
                    <div class="parcel-stat-value" id="parcelCountShipmate">{{ number_format($providerTotals['shipmate']) }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="parcel-stat-card p-3">
                    <div class="parcel-stat-label mb-1">Total</div>
                    <div class="parcel-stat-value" id="parcelCountTotal">{{ number_format($providerTotals['total']) }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <x-datatable id="scanTrackingTable"
                    ajax="{{ route('console.scanTracking.index') }}"
                    :filters="['from_date', 'to_date']"
                    :columns="[
                        ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'label' => '#', 'orderable' => false, 'searchable' => false],
                        ['data' => 'date_label', 'name' => 'date', 'label' => 'Date', 'default_order' => 'desc'],
                        ['data' => 'evri', 'name' => 'evri', 'label' => 'Evri Standard', 'render' => 'renderParcelCount'],
                        ['data' => 'veeqo', 'name' => 'veeqo', 'label' => 'Prime', 'render' => 'renderParcelCount'],
                        ['data' => 'shipmate', 'name' => 'shipmate', 'label' => 'Parcelforce', 'render' => 'renderParcelCount'],
                        ['data' => 'total', 'name' => 'total', 'label' => 'Total', 'render' => 'renderParcelCount'],
                    ]" />
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function buildScanTrackingUrl() {
            let url = `{{ route('console.scanTracking.index') }}`;
            const params = [];
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;

            if (fromDate) {
                params.push(`from_date=${encodeURIComponent(fromDate)}`);
            }
            if (toDate) {
                params.push(`to_date=${encodeURIComponent(toDate)}`);
            }

            if (params.length) {
                url += `?${params.join('&')}`;
            }

            return url;
        }

        function formatScanTrackingPeriodLabel(fromDate, toDate) {
            if (!fromDate || !toDate) {
                return 'Last 30 days';
            }

            const from = new Date(fromDate + 'T00:00:00');
            const to = new Date(toDate + 'T00:00:00');
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            const label = `${from.toLocaleDateString('en-GB', options)} – ${to.toLocaleDateString('en-GB', options)}`;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const expectedFrom = new Date(today);
            expectedFrom.setDate(expectedFrom.getDate() - 29);

            if (from.getTime() === expectedFrom.getTime() && to.getTime() === today.getTime()) {
                return 'Last 30 days';
            }

            return label;
        }

        function updateParcelCounts(providerTotals) {
            const totals = providerTotals || {};
            const setCount = (id, value) => {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = Number(value || 0).toLocaleString('en-US');
                }
            };

            setCount('parcelCountEvri', totals.evri);
            setCount('parcelCountVeeqo', totals.veeqo);
            setCount('parcelCountShipmate', totals.shipmate);
            setCount('parcelCountTotal', totals.total);
        }

        function updateScanTrackingSummary(json, fromDate, toDate) {
            const periodEl = document.getElementById('scanTrackingPeriodLabel');

            if (periodEl) {
                periodEl.textContent = formatScanTrackingPeriodLabel(fromDate, toDate);
            }

            updateParcelCounts(json?.providerTotals);
        }

        function applyScanTrackingFilters() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;

            if ((fromDate && !toDate) || (!fromDate && toDate)) {
                toastr.warning('Please select both From and To dates.');
                return;
            }

            const table = $('#scanTrackingTable').DataTable();
            table.ajax.url(buildScanTrackingUrl()).load(function(json) {
                updateScanTrackingSummary(json, fromDate, toDate);
            });
        }

        function resetScanTrackingFilters() {
            const today = new Date();
            const fromDate = new Date(today);
            fromDate.setDate(fromDate.getDate() - 29);

            const toIsoDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const fromValue = toIsoDate(fromDate);
            const toValue = toIsoDate(today);

            document.getElementById('from_date').value = fromValue;
            document.getElementById('to_date').value = toValue;

            const table = $('#scanTrackingTable').DataTable();
            table.ajax.url(`{{ route('console.scanTracking.index') }}`).load(function(json) {
                updateScanTrackingSummary(json, fromValue, toValue);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            $('#scanTrackingTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.providerTotals) {
                    return;
                }

                updateScanTrackingSummary(
                    json,
                    document.getElementById('from_date').value,
                    document.getElementById('to_date').value
                );
            });

            $('#scanTrackingTable').on('preDraw.dt', function() {
                document.querySelectorAll('#scanTrackingTable [data-bs-toggle="popover"]').forEach(function(el) {
                    var instance = bootstrap.Popover.getInstance(el);
                    if (instance) {
                        instance.dispose();
                    }
                });
            });

            $('#scanTrackingTable').on('draw.dt', function() {
                var popoverTriggerList = [].slice.call(document.querySelectorAll('#scanTrackingTable [data-bs-toggle="popover"]'));
                popoverTriggerList.map(function (popoverTriggerEl) {
                    var existing = bootstrap.Popover.getInstance(popoverTriggerEl);
                    if (existing) {
                        existing.dispose();
                    }
                    return new bootstrap.Popover(popoverTriggerEl, {
                        html: true,
                        trigger: 'click'
                    });
                });
            });

            // Close any open popovers when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('[data-bs-toggle="popover"]') && !e.target.closest('.popover')) {
                    document.querySelectorAll('#scanTrackingTable [data-bs-toggle="popover"]').forEach(function(el) {
                        var instance = bootstrap.Popover.getInstance(el);
                        if (instance) {
                            instance.hide();
                        }
                    });
                }
            });

            // Close other open popovers when a new one is opened
            document.addEventListener('show.bs.popover', function(e) {
                document.querySelectorAll('#scanTrackingTable [data-bs-toggle="popover"]').forEach(function(el) {
                    if (el !== e.target) {
                        var instance = bootstrap.Popover.getInstance(el);
                        if (instance) {
                            instance.hide();
                        }
                    }
                });
            });
        });

        function renderParcelCount(data, type, row, meta) {
            const colName = meta.settings.aoColumns[meta.col].data;
            const original = parseInt(data) || 0;
            const additional = parseInt(row[colName + '_additional']) || 0;
            const total = original + additional;
            
            if (type !== 'display') return total;
            
            if (additional > 0) {
                return `${total} <i class="icon-base ti tabler-eye text-primary ms-2" tabindex="0" style="cursor: pointer; vertical-align: middle;" data-bs-toggle="popover" data-bs-trigger="click" data-bs-placement="top" data-bs-content="Includes <b>${additional}</b> additional label(s)"></i>`;
            }
            
            return total;
        }
    </script>
@endpush
