@extends('console.layout.app')

@section('title', 'Replacements')

@push('styles')
<style>
    .dt-buttons {
        display: none !important;
    }

    .replacement-card {
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        border: 2px solid transparent;
        border-radius: 8px;
    }
    .replacement-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .replacement-card.selected {
        border-color: #0d6efd;
        background-color: #e7f3ff;
    }

    /* --- Tab styling (same as Orders page) --- */
    .nav-tabs {
        justify-content: center;
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 2rem;
    }

    .nav-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 3px solid transparent;
        font-size: 1.1rem;
        font-weight: 500;
        padding: 1rem 2rem;
        margin: 0 0.5rem;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        min-width: 150px;
        text-align: center;
    }

    .nav-tabs .nav-link:hover {
        color: #0d6efd;
        background-color: #f8f9fa;
        border-bottom: 3px solid #dee2e6;
    }

    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd;
        background-color: #e7f3ff;
        font-weight: 600;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15);
    }

    .tab-content {
        padding-top: 1rem;
    }

    /* Preview styles for modal */

    .label-card-container { display: flex; flex-direction: column; align-items: center; }
    .label-card { background: transparent; border: none; box-shadow: none; width: 4in; max-width: 100%; margin: 0 auto; }
    .label-card .card-body { padding: 0.5rem 0 1rem; }

    .production-label {
        --pl-border: #111;
        --pl-font: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
        --pl-outer-gap: 0.08in;
        width: calc(4in - (2 * var(--pl-outer-gap)));
        height: calc(6in - (2 * var(--pl-outer-gap)));
        max-width: calc(100% - 0.16in);
        margin: var(--pl-outer-gap) auto;
        color: #111; background: #fff; font-family: var(--pl-font);
        box-sizing: border-box; border: 2px solid var(--pl-border);
        border-radius: 10px; padding: 0.07in;
        display: flex; flex-direction: column; overflow: hidden;
    }
    .production-label *, .production-label *::before, .production-label *::after { box-sizing: border-box; }
    .pl-section { border: 1.5px solid var(--pl-border); border-radius: 6px; margin-bottom: 0.08in; overflow: hidden; background: #fff; flex: 0 0 auto; }
    .pl-section:last-child { margin-bottom: 0; flex: 1 1 auto; }
    .pl-codes { display: flex; align-items: stretch; min-height: 0.95in; padding: 0.06in 0.08in; gap: 0; border: none; border-radius: 0; }
    .pl-barcode { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 0 10px 0 4px; min-width: 0; }
    .pl-barcode-img { width: 100%; max-height: 66px; object-fit: contain; display: block; }
    .pl-barcode-text { margin-top: 3px; font-size: 13px; font-weight: 700; letter-spacing: 0.02em; text-align: center; word-break: break-all; }
    .pl-qr { flex: 0 0 74px; display: flex; align-items: center; justify-content: center; border-left: 1.5px solid var(--pl-border); padding-left: 8px; }
    .pl-qr-img { width: 76px; height: 90px; object-fit: contain; display: block; }
    .pl-product { display: flex; gap: 8px; padding: 8px 10px; align-items: stretch; margin-bottom: 0.06in; }
    .pl-product-main { flex: 1 1 auto; min-width: 0; text-align: left; }
    .pl-title { font-size: 22px; font-weight: 900; line-height: 1.1; text-transform: uppercase; letter-spacing: 0.01em; }
    .pl-attrs { margin-top: 6px; font-size: 11px; font-weight: 700; line-height: 1.3; text-transform: uppercase; }
    .pl-badges { flex: 0 0 78px; display: flex; flex-direction: column; gap: 4px; }
    .pl-badge { border: 1.5px solid var(--pl-border); border-radius: 4px; min-height: 28px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 4px; line-height: 1.1; word-break: break-word; }
    .pl-badge-fill { background: #111; color: #fff; border-color: #111; }
    .pl-badge-qty { font-size: 22px; font-weight: 900; min-height: 34px; }
    .pl-order { position: relative; padding: 22px 10px 8px; min-height: 0; overflow: visible; }
    .pl-tab { position: absolute; top: 8px; left: 0px; transform: translateY(-50%); background: #111; color: #fff; font-size: 9px; font-weight: 800; letter-spacing: 0.04em; padding: 2px 7px; border-radius: 2px; text-transform: uppercase; }
    .pl-order-body { display: block; }
    .pl-order-main { font-size: 11px; font-weight: 700; line-height: 1.35; text-transform: uppercase; text-align: left; }
    .pl-order-label { font-weight: 800; }
    .pl-order-source, .pl-order-company { font-weight: 800; margin-top: 1px; }
    .pl-order-company { margin-top: 4px; }
    .pl-order-reason { font-weight: 800; margin-top: 2px; color: #111; letter-spacing: 0.02em; }
    .pl-note { min-height: 0.72in; padding: 0.07in 0.1in 0.09in; display: flex; flex-direction: column; flex-shrink: 0; }
    .pl-note-header { font-size: 11px; font-weight: 800; text-transform: uppercase; text-align: left; margin-bottom: 4px; }
    .pl-note-body { flex: 1 1 auto; min-height: 0.38in; }
    .label-navigation { text-align: center; margin-top: 12px; }
    #print-labels-area .production-label { max-width: 4in; }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Search / Create Section --}}
    <div class="card mb-4">
        <div class="card-body py-4 px-5">
            <h5 class="card-title mb-1"><i class="fas fa-sync-alt me-2"></i>Create Replacement</h5>
            <p class="text-muted mb-4" style="font-size: 0.88rem;">Search an order number to create a new replacement request</p>
            <div class="d-flex gap-3 align-items-center">
                <div class="input-group input-group-lg flex-grow-1" style="max-width: 560px;">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        type="text"
                        id="orderSearchInput"
                        class="form-control"
                        placeholder="Enter Order Number e.g. 202-1234567-8901234"
                        aria-label="Search Order Number"
                        style="font-size: 1rem;"
                        onkeydown="if(event.key==='Enter') searchOrder()"
                    >
                </div>
                <button class="btn btn-primary btn-lg px-4 fw-semibold" onclick="searchOrder()" style="white-space: nowrap;">
                    <i class="fas fa-search me-2"></i>Search &amp; Create
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Replacement Records</h5>
            <a href="#" id="bulkPrintBtn" class="btn btn-primary d-none" onclick="bulkPrintLabels(event)">
                <i class="fas fa-print me-1"></i>Bulk Print
            </a>
        </div>

        <div class="card-body mt-4">

            {{-- Replacement Tabs --}}
            <ul class="nav nav-tabs" id="replacementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-replacement-tab"
                        data-bs-toggle="tab" data-bs-target="#pendingReplacement"
                        type="button" role="tab" aria-controls="pendingReplacement" aria-selected="true">
                        <i class="fas fa-clock me-2"></i>Pending Replacement
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="printed-replacement-tab"
                        data-bs-toggle="tab" data-bs-target="#printedReplacement"
                        type="button" role="tab" aria-controls="printedReplacement" aria-selected="false">
                        <i class="fas fa-check-circle me-2"></i>Printed Replacement
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="replacementTabsContent">

                {{-- Pending Replacement Tab --}}
                <div class="tab-pane fade show active" id="pendingReplacement" role="tabpanel" aria-labelledby="pending-replacement-tab">
                    <div class="mt-4">
                        <x-datatable id="pendingReplacementsTable"
                            ajax="{{ route('console.replacements.index') }}?is_printed=0"
                            checkbox-callback="onReplacementCheckboxCheck"
                            :columns="[
                                [
                                    'data' => 'checkbox',
                                    'name' => 'checkbox',
                                    'label' => '<input type=\'checkbox\' id=\'select-all-pending\' class=\'form-check-input\' aria-label=\'Select all pending replacements\'>',
                                    'orderable' => false,
                                    'searchable' => false,
                                    'render' => true,
                                ],
                                ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order Number'],
                                ['data' => 'customer', 'name' => 'customer', 'label' => 'Customer'],
                                ['data' => 'reason', 'name' => 'reason', 'label' => 'Replacement Reason'],
                                ['data' => 'description', 'name' => 'description', 'label' => 'Description'],
                                ['data' => 'selected_items', 'name' => 'selected_items', 'label' => 'Selected Items', 'render' => true],
                                ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'status', 'name' => 'status', 'label' => 'Status', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'source', 'name' => 'source', 'label' => 'Source', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'created_date', 'name' => 'created_date', 'label' => 'Created Date', 'default_order' => 'desc'],
                                [
                                    'data' => 'action',
                                    'name' => 'action',
                                    'label' => 'Action',
                                    'orderable' => false,
                                    'searchable' => false,
                                    'render' => true,
                                ],
                            ]"
                        />
                    </div>
                </div>

                {{-- Printed Replacement Tab --}}
                <div class="tab-pane fade" id="printedReplacement" role="tabpanel" aria-labelledby="printed-replacement-tab">
                    <div class="mt-4">
                        <x-datatable id="printedReplacementsTable"
                            ajax="{{ route('console.replacements.index') }}?is_printed=1"
                            checkbox-callback="onReplacementCheckboxCheck"
                            :columns="[
                                [
                                    'data' => 'checkbox',
                                    'name' => 'checkbox',
                                    'label' => '<input type=\'checkbox\' id=\'select-all-printed\' class=\'form-check-input\' aria-label=\'Select all printed replacements\'>',
                                    'orderable' => false,
                                    'searchable' => false,
                                    'render' => true,
                                ],
                                ['data' => 'order_id', 'name' => 'order_id', 'label' => 'Order Number'],
                                ['data' => 'customer', 'name' => 'customer', 'label' => 'Customer'],
                                ['data' => 'reason', 'name' => 'reason', 'label' => 'Replacement Reason'],
                                ['data' => 'description', 'name' => 'description', 'label' => 'Description'],
                                ['data' => 'selected_items', 'name' => 'selected_items', 'label' => 'Selected Items', 'render' => true],
                                ['data' => 'material_type', 'name' => 'material_type', 'label' => 'Material Type', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'edging', 'name' => 'edging', 'label' => 'Edging', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'status', 'name' => 'status', 'label' => 'Status', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'source', 'name' => 'source', 'label' => 'Source', 'render' => true, 'orderable' => false, 'searchable' => false],
                                ['data' => 'created_date', 'name' => 'created_date', 'label' => 'Created Date'],
                                ['data' => 'printed_date', 'name' => 'printed_date', 'label' => 'Printed Date', 'orderable' => false, 'searchable' => false],
                                [
                                    'data' => 'printed_action',
                                    'name' => 'printed_action',
                                    'label' => 'Action',
                                    'orderable' => false,
                                    'searchable' => false,
                                    'render' => true,
                                ],
                            ]"
                        />
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Create Replacement Modal -->
<div class="modal fade" id="createReplacementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Replacement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Select Items to Replace</h6>
                <div id="orderItemsContainer" class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                    <!-- Cards will be injected here -->
                </div>

                <form id="replacementForm">
                    <input type="hidden" id="modalOrderId" name="modalOrderId" value="">

                    <div class="mb-3">
                        <label for="replacementReason" class="form-label">Replacement Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="replacementReason" name="reason" required>
                            <option value="" disabled selected>Select a reason...</option>
                            <option value="SEND NEW ORDER (ORDER NEVER BEEN DISPATCHED)">SEND NEW ORDER (ORDER NEVER BEEN DISPATCHED)</option>
                            <option value="SEND NEW ORDER (ORDER LOST IN TRANSIT)">SEND NEW ORDER (ORDER LOST IN TRANSIT)</option>
                            <option value="WRONG ORDER (SEND CORRECT ORDER)">WRONG ORDER (SEND CORRECT ORDER)</option>
                            <option value="SEND NEW ORDER">SEND NEW ORDER</option>
                            <option value="SEND NEW MATS">SEND NEW MATS</option>
                            <option value="WRONG MATS (SEND CORRECT MATS)">WRONG MATS (SEND CORRECT MATS)</option>
                            <option value="WRONG EDGING COLOUR (SEND MATS WITH CORRECT EDGING)">WRONG EDGING COLOUR (SEND MATS WITH CORRECT EDGING)</option>
                            <option value="CLIPS MISSING SEND CLIPS">CLIPS MISSING SEND CLIPS</option>
                            <option value="SEND DRIVER MAT">SEND DRIVER MAT</option>
                            <option value="SEND PASSENGER MAT">SEND PASSENGER MAT</option>
                            <option value="SEND DRIVER & PASSENGER MATS">SEND DRIVER & PASSENGER MATS</option>
                            <option value="SEND REAR MATS">SEND REAR MATS</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="replacementDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="replacementDescription" name="description" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveReplacement()">Save Replacement</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Labels Modal -->
<div class="modal fade" id="printLabelsModal" tabindex="-1" aria-labelledby="printLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printLabelsModalLabel">Print Labels</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: calc(100vh - 140px);">
                <div id="labelContainer" class="w-100 h-100 d-flex flex-column justify-content-start align-items-center position-relative" style="padding-top: 20px;">
                    <div class="position-absolute" style="top: 20px; right: 20px; z-index: 9999;">
                        <button class="btn btn-primary btn-sm print-single-label" onclick="printCurrentLabel()">
                            <i class="ti ti-printer me-1"></i>
                            Print
                        </button>
                    </div>

                    <button class="btn btn-outline-primary btn-lg prev-label-btn position-absolute" style="left: 20px; top: 50%; transform: translateY(-50%); z-index: 9999; background-color: white; border: 2px solid #0d6efd; box-shadow: 0 4px 8px rgba(0,0,0,0.2);" onclick="navigateLabel(-1)">
                        &larr;
                    </button>

                    <button class="btn btn-outline-primary btn-lg next-label-btn position-absolute" style="right: 20px; top: 50%; transform: translateY(-50%); z-index: 9999; background-color: white; border: 2px solid #0d6efd; box-shadow: 0 4px 8px rgba(0,0,0,0.2);" onclick="navigateLabel(1)">
                        &rarr;
                    </button>

                    <div id="labelCardsWrapper" class="w-100">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printAllLabels()">
                    <i class="ti ti-printer me-1"></i>
                    Print All Labels
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteReplacementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Replacement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this replacement record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteReplacementBtn">
                    <i class="fas fa-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let selectedOrderItems = [];
    // Track which replacement IDs are being processed for printing (so we can mark them after)
    let pendingPrintIds = [];

    // ─── Checkbox / bulk print button ───────────────────────────────────────────
    function onReplacementCheckboxCheck(tableElement) {
        // Only show Bulk Print when we are on the Pending tab
        const isPendingTabActive = document.getElementById('pendingReplacement').classList.contains('show');
        const checkedCount = tableElement.querySelectorAll('tbody input.row-checkbox:checked').length;
        const bulkPrintBtn = document.getElementById('bulkPrintBtn');
        if (bulkPrintBtn) {
            bulkPrintBtn.classList.toggle('d-none', checkedCount === 0 || !isPendingTabActive);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Re-evaluate button visibility whenever either table draws
        $('#pendingReplacementsTable').on('draw.dt', function () {
            onReplacementCheckboxCheck(document.getElementById('pendingReplacementsTable'));
        });
        $('#printedReplacementsTable').on('draw.dt', function () {
            onReplacementCheckboxCheck(document.getElementById('printedReplacementsTable'));
        });

        // Hide bulk print when switching to Printed tab
        document.getElementById('printed-replacement-tab').addEventListener('shown.bs.tab', function () {
            document.getElementById('bulkPrintBtn').classList.add('d-none');
        });
        document.getElementById('pending-replacement-tab').addEventListener('shown.bs.tab', function () {
            // Re-check in case something is selected
            onReplacementCheckboxCheck(document.getElementById('pendingReplacementsTable'));
        });
    });

    function getSelectedPendingIds() {
        return Array.from(document.querySelectorAll('#pendingReplacementsTable tbody input.row-checkbox:checked'))
            .map(cb => cb.value)
            .filter(Boolean);
    }

    function bulkPrintLabels(event) {
        event.preventDefault();
        const ids = getSelectedPendingIds();
        if (!ids.length) {
            toastr.warning('Please select at least one replacement.');
            return;
        }
        openReplacementPrintModal(ids);
    }

    // ─── Label navigation ────────────────────────────────────────────────────────
    let currentLabelIndex = 0;

    function navigateLabel(dir) {
        const cards = document.querySelectorAll('#labelCardsWrapper .label-card-container');
        if (!cards.length) return;

        cards[currentLabelIndex].classList.remove('active');
        cards[currentLabelIndex].classList.add('d-none');

        currentLabelIndex += dir;
        if (currentLabelIndex < 0) currentLabelIndex = 0;
        if (currentLabelIndex >= cards.length) currentLabelIndex = cards.length - 1;

        cards[currentLabelIndex].classList.remove('d-none');
        cards[currentLabelIndex].classList.add('active');

        document.querySelector('.prev-label-btn').disabled = (currentLabelIndex === 0);
        document.querySelector('.next-label-btn').disabled = (currentLabelIndex === cards.length - 1);
    }

    // ─── Print helpers ───────────────────────────────────────────────────────────
    function getProductionLabelPrintStyles() {
        return `
            @page { size: 4in 6in; margin: 0; }
            html, body {
                margin: 0; padding: 0; width: 4in; background: #fff; color: #111;
            }
            body {
                box-sizing: border-box;
                font-family: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            .label-page {
                width: 4in; height: 6in; box-sizing: border-box; padding: 0.08in;
                page-break-after: always; break-after: page; overflow: hidden;
            }
            .label-page:last-child { page-break-after: auto; break-after: auto; }
            body > .production-label {
                width: calc(4in - 0.16in); height: calc(6in - 0.16in); margin: 0.08in;
            }
            .production-label {
                --pl-border: #111; width: 100%; height: 100%; max-width: none; margin: 0;
                color: #111; background: #fff;
                font-family: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
                box-sizing: border-box; border: 2px solid #111; border-radius: 10px; padding: 0.07in;
                display: flex; flex-direction: column; overflow: hidden;
            }
            .production-label *, .production-label *::before, .production-label *::after { box-sizing: border-box; }
            .pl-section { border: 1.5px solid #111; border-radius: 6px; margin-bottom: 0.08in; overflow: hidden; background: #fff; flex: 0 0 auto; }
            .pl-section:last-child { margin-bottom: 0; flex: 1 1 auto; }
            .pl-codes { display: flex; align-items: stretch; min-height: 0.95in; padding: 0.06in 0.08in; gap: 0; border: none; border-radius: 0; }
            .pl-barcode { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 0 10px 0 4px; min-width: 0; }
            .pl-barcode-img { width: 100%; max-height: 66px; object-fit: contain; display: block; }
            .pl-barcode-text { margin-top: 3px; font-size: 13px; font-weight: 700; letter-spacing: 0.02em; text-align: center; word-break: break-all; }
            .pl-qr { flex: 0 0 74px; display: flex; align-items: center; justify-content: center; border-left: 1.5px solid #111; padding-left: 8px; }
            .pl-qr-img { width: 76px; height: 90px; object-fit: contain; display: block; }
            .pl-product { display: flex; gap: 8px; padding: 8px 10px; align-items: stretch; margin-bottom: 0.06in; }
            .pl-product-main { flex: 1 1 auto; min-width: 0; text-align: left; }
            .pl-title { font-size: 22px; font-weight: 900; line-height: 1.1; text-transform: uppercase; letter-spacing: 0.01em; }
            .pl-attrs { margin-top: 6px; font-size: 11px; font-weight: 700; line-height: 1.3; text-transform: uppercase; }
            .pl-badges { flex: 0 0 78px; display: flex; flex-direction: column; gap: 4px; }
            .pl-badge { border: 1.5px solid #111; border-radius: 4px; min-height: 28px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 4px; line-height: 1.1; word-break: break-word; }
            .pl-badge-fill { background: #111 !important; color: #fff !important; border-color: #111 !important; }
            .pl-badge-qty { font-size: 22px; font-weight: 900; min-height: 34px; }
            .pl-order { position: relative; padding: 22px 10px 8px; min-height: 0; overflow: visible; margin-top: 0.05in; }
            .pl-tab { position: absolute; top: 8px; left: 0px; transform: translateY(-50%); background: #111 !important; color: #fff !important; font-size: 9px; font-weight: 800; letter-spacing: 0.04em; padding: 2px 7px; border-radius: 2px; text-transform: uppercase; }
            .pl-order-body { display: block; }
            .pl-order-main { font-size: 11px; font-weight: 700; line-height: 1.35; text-transform: uppercase; text-align: left; }
            .pl-order-label { font-weight: 800; }
            .pl-order-source, .pl-order-company { font-weight: 800; margin-top: 1px; }
            .pl-order-company { margin-top: 4px; }
            .pl-order-reason { font-weight: 800; margin-top: 2px; color: #111; letter-spacing: 0.02em; }
            .pl-note { min-height: 0.72in; padding: 0.07in 0.1in 0.09in; display: flex; flex-direction: column; flex-shrink: 0; }
            .pl-note-header { font-size: 11px; font-weight: 800; text-transform: uppercase; text-align: left; margin-bottom: 4px; }
            .pl-note-body { flex: 1 1 auto; min-height: 0.38in; }
            .pl-reason-row { background: #111 !important; color: #fff !important; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; padding: 4px 10px; text-align: center; word-break: break-word; line-height: 1.3; flex-shrink: 0; }
        `;
    }

    function openLabelPrintWindow(title, bodyHtml) {
        var printWindow = window.open('', '_blank');
        if (!printWindow) return;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>${title}</title>
                <style>${getProductionLabelPrintStyles()}</style>
            </head>
            <body>
                ${bodyHtml}
            </body>
            </html>
        `);
        printWindow.document.close();

        var triggerPrint = function () {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };

        if (printWindow.document.readyState === 'complete') {
            setTimeout(triggerPrint, 250);
        } else {
            printWindow.onload = function () { setTimeout(triggerPrint, 250); };
        }
    }

    function printAllLabels() {
        var bodyHtml = '';
        var cards = document.querySelectorAll('#labelCardsWrapper .label-card-container');

        cards.forEach(function (card) {
            var labelShell = card.querySelector('.production-label');
            if (labelShell) {
                bodyHtml += '<div class="label-page">' + labelShell.outerHTML + '</div>';
            }
        });

        openLabelPrintWindow('Print All Labels', bodyHtml);

        // Mark as printed after triggering print
        markReplacementsAsPrinted(pendingPrintIds);
    }

    function printCurrentLabel() {
        var cards = document.querySelectorAll('#labelCardsWrapper .label-card-container');
        if (!cards.length) return;

        var currentCard = cards[currentLabelIndex];
        var labelShell = currentCard.querySelector('.production-label');
        if (labelShell) {
            openLabelPrintWindow('Print Label', labelShell.outerHTML);
        }

        // Mark as printed after triggering print
        markReplacementsAsPrinted(pendingPrintIds);
    }

    /**
     * Call backend to mark replacement(s) as printed, then refresh relevant tables.
     */
    function markReplacementsAsPrinted(ids) {
        if (!ids || !ids.length) return;

        fetch('{{ route('console.replacements.markAsPrinted') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Move rows: reload both tabs
                if ($.fn.DataTable.isDataTable('#pendingReplacementsTable')) {
                    $('#pendingReplacementsTable').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#printedReplacementsTable')) {
                    $('#printedReplacementsTable').DataTable().ajax.reload(null, false);
                }
            }
        })
        .catch(err => console.error('markAsPrinted error:', err));

        pendingPrintIds = [];
    }

    // ─── Open print modal ────────────────────────────────────────────────────────
    function openReplacementPrintModal(ids) {
        const idsArray = Array.isArray(ids) ? ids : [ids];
        pendingPrintIds = idsArray.map(String); // store for later markAsPrinted call

        const idsStr = idsArray.join(',');
        fetch(`{{ route('console.replacements.print') }}?ids=${idsStr}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const wrapper = document.getElementById('labelCardsWrapper');
                wrapper.innerHTML = data.html;

                currentLabelIndex = 0;
                const cards = wrapper.querySelectorAll('.label-card-container');
                if (cards.length) {
                    cards.forEach((c, i) => {
                        if (i > 0) {
                            c.classList.remove('active');
                            c.classList.add('d-none');
                        }
                    });
                    document.querySelector('.prev-label-btn').disabled = true;
                    document.querySelector('.next-label-btn').disabled = (cards.length <= 1);
                }

                new bootstrap.Modal(document.getElementById('printLabelsModal')).show();
            } else {
                toastr.error(data.message || 'Error loading labels.');
            }
        })
        .catch(err => {
            console.error(err);
            toastr.error('Failed to load labels.');
        });
    }

    // ─── Search order ────────────────────────────────────────────────────────────
    function searchOrder() {
        const orderId = document.getElementById('orderSearchInput').value.trim();
        if (!orderId) {
            toastr.error('Please enter an Order Number to search.');
            return;
        }

        fetch(`{{ route('console.replacements.search') }}?order_id=${encodeURIComponent(orderId)}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openReplacementModal(orderId, data.orders);
            } else {
                toastr.error(data.message || 'Order not found.');
            }
        })
        .catch(err => {
            console.error(err);
            toastr.error('An error occurred while searching for the order.');
        });
    }

    function openReplacementModal(orderId, orders) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('replacementReason').value = '';
        document.getElementById('replacementDescription').value = '';
        selectedOrderItems = [];

        const container = document.getElementById('orderItemsContainer');
        container.innerHTML = '';

        orders.forEach(item => {
            const cardHTML = `
                <div class="col">
                    <div class="card batch-card replacement-card h-100" data-item-id="${item.id}" onclick="toggleItemSelection(this, ${item.id})">
                        <div class="card-body d-flex flex-column">
                            <div class="order-detail-content flex-grow-1">
                                <div class="order-item fw-bold mb-2 text-primary">Order ID: ${item.order_id || 'N/A'}</div>
                                <div class="order-item"><span class="fw-semibold">Order Number:</span> <span class="ms-1">${item.order_item_id || 'N/A'}</span></div>
                                <div class="order-item"><span class="fw-semibold">Material:</span> <span class="ms-1">${item.material_type || 'N/A'}</span></div>
                                <div class="order-item"><span class="fw-semibold">Edging:</span> <span class="ms-1">${item.edging || 'N/A'}</span></div>
                                <div class="order-item"><span class="fw-semibold">Status:</span> <span class="ms-1">${item.status || 'N/A'}</span></div>
                                <div class="order-item"><span class="fw-semibold">Product:</span> <span class="ms-1 product-ellipsis" title="${item.product}">${item.product}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', cardHTML);
        });

        const modal = new bootstrap.Modal(document.getElementById('createReplacementModal'));
        modal.show();
    }

    // ─── Delete replacement ──────────────────────────────────────────────────────
    let pendingDeleteId = null;

    function deleteReplacement(id) {
        pendingDeleteId = id;
        new bootstrap.Modal(document.getElementById('deleteReplacementModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('confirmDeleteReplacementBtn').addEventListener('click', function () {
            if (!pendingDeleteId) return;

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

            fetch(`{{ url('console/replacements') }}/${pendingDeleteId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                bootstrap.Modal.getInstance(document.getElementById('deleteReplacementModal')).hide();
                pendingDeleteId = null;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';

                if (data.success) {
                    toastr.success(data.message || 'Replacement deleted successfully.');
                    if ($.fn.DataTable.isDataTable('#pendingReplacementsTable')) {
                        $('#pendingReplacementsTable').DataTable().ajax.reload(null, false);
                    }
                    if ($.fn.DataTable.isDataTable('#printedReplacementsTable')) {
                        $('#printedReplacementsTable').DataTable().ajax.reload(null, false);
                    }
                } else {
                    toastr.error(data.message || 'Error deleting replacement.');
                }
            })
            .catch(err => {
                console.error(err);
                bootstrap.Modal.getInstance(document.getElementById('deleteReplacementModal')).hide();
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
                toastr.error('An unexpected error occurred while deleting.');
            });
        });
    });

    // ─── Item selection for create modal ────────────────────────────────────────
    function toggleItemSelection(element, itemId) {
        const index = selectedOrderItems.indexOf(itemId);
        if (index > -1) {
            selectedOrderItems.splice(index, 1);
            element.classList.remove('selected');
        } else {
            selectedOrderItems.push(itemId);
            element.classList.add('selected');
        }
    }

    // ─── Save replacement ────────────────────────────────────────────────────────
    function saveReplacement() {
        if (selectedOrderItems.length === 0) {
            toastr.error('Please select at least one item to replace.');
            return;
        }

        const reason = document.getElementById('replacementReason').value;
        if (!reason) {
            toastr.error('Please select a replacement reason.');
            return;
        }

        const orderId = document.getElementById('modalOrderId').value;
        const description = document.getElementById('replacementDescription').value;

        fetch(`{{ route('console.replacements.store') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                selected_items: selectedOrderItems,
                reason: reason,
                description: description
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                const modalEl = document.getElementById('createReplacementModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                // Only reload pending tab (new records are always pending)
                if ($.fn.DataTable.isDataTable('#pendingReplacementsTable')) {
                    $('#pendingReplacementsTable').DataTable().ajax.reload(null, false);
                }
            } else {
                toastr.error(data.message || 'Error creating replacement.');
            }
        })
        .catch(err => {
            console.error(err);
            toastr.error('An unexpected error occurred.');
        });
    }
</script>
@endpush
