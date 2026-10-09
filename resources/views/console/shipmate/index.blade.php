@extends('console.layout.app')

@section('title', 'Manual Label Printing')

@push('styles')
    <style>
        .resultBox {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
            padding: 20px;
            border: 1px solid #8ba3ce;
            border-radius: 5px;
        }

        table {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
        }

        .cursor-pointer {
            cursor: pointer !important;
        }

        .internal_parent {
            width: 33%;
        }

        @media only screen and (max-width: 600px) {
            .internal_parent {
                width: 100%;
            }
        }

        .shipping-step-panel[hidden] {
            display: none !important;
        }

        .shipping-session-banner {
            border: 1px solid rgba(13, 110, 253, 0.2);
            background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
        }

        .shipping-session-banner .scan-pill {
            min-width: 2.5rem;
            text-align: center;
        }

        .shipmate-label-count {
            z-index: 1;
            min-width: 4.5rem;
        }

        .shipmate-label-count .shipmateLabelCount {
            min-width: 2.25rem;
        }

        .shipping-item-select-card {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .shipping-item-select-card:hover,
        .shipping-item-select-card:focus-within {
            border-color: #0d6efd;
            box-shadow: rgba(13, 110, 253, 0.15) 0px 8px 24px 0px;
        }
    </style>
@endpush

@section('content')
    <div class="container mt-5">
        <div id="shipmateStepStitcher" class="row g-3 shipping-step-panel">
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 1 of 3</p>
                <label for="shipmateStitcherSelect" class="form-label fw-semibold">Stitcher</label>
                <select id="shipmateStitcherSelect" class="form-select" required>
                    <option value="">— Select stitcher —</option>
                    @foreach ($stitchers as $stitcher)
                        <option value="{{ $stitcher->id }}">{{ $stitcher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-12 text-center">
                <button type="button" id="shipmateStitcherNextBtn" class="btn btn-primary mt-2">
                    Next
                </button>
            </div>
        </div>

        <div id="shipmateStepOrder" class="row g-3 shipping-step-panel" hidden>
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 2 of 3</p>
                <div class="shipping-session-banner rounded-3 p-3 shadow-sm mb-3">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-md min-w-0">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 44px; height: 44px; font-size: 1.1rem; font-weight: 600;"
                                     id="shipmateStep2StitcherInitial"
                                     aria-hidden="true">—</div>
                                <div class="min-w-0">
                                    <div class="text-uppercase text-muted small mb-0" style="letter-spacing: 0.06em;">Printing as</div>
                                    <div id="shipmateStep2StitcherName" class="fs-5 fw-semibold text-dark text-truncate">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-auto text-center">
                            <button type="button" id="shipmateFinishBtn" class="btn btn-success px-4 py-2 fw-semibold">
                                Finish
                            </button>
                        </div>
                        <div class="col-12 col-md">
                            <div class="d-flex align-items-center justify-content-center justify-content-md-end gap-2">
                                <div class="vr d-none d-md-block align-self-stretch my-1 opacity-50"></div>
                                <span class="text-muted small mb-0 d-none d-md-inline">Number of Orders</span>
                                <span class="text-muted small mb-0 d-md-none">Orders</span>
                                <span id="shipmateStep2ScanCount" class="badge bg-primary scan-pill fs-6 px-3 py-2 rounded-pill">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                <label for="shipmateOrderId" class="form-label fw-semibold">Order number</label>
                <input type="text" id="shipmateOrderId" class="form-control" placeholder="Scan or type order number">
            </div>
            <div class="col-lg-12 text-center">
                <button type="button" id="shipmateSearchOrderBtn" class="btn btn-primary mt-2" onclick="shipmateSearchOrder(this)">
                    SEARCH NOW
                </button>
            </div>
        </div>

        <div id="shipmateStepItemSelect" class="row g-3 shipping-step-panel my-4" hidden>
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 3 of 3</p>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Select an item to print</h5>
                        <p class="text-muted small mb-0">This order has multiple items. Choose one to continue.</p>
                    </div>
                    <button type="button" id="shipmateItemSelectBackBtn" class="btn btn-outline-secondary btn-sm">
                        Back to search
                    </button>
                </div>
                <div id="shipmateItemSelectContainer" class="row g-3"></div>
            </div>
        </div>

        <div id="shipmateResultsContainer" class="row my-5"></div>
    </div>
@endsection

@push('scripts')
    <script>
        let shipmateSessionScanCount = 0;
        let shipmatePendingOrders = [];
        const SHIPMATE_MAX_LABEL_PRINT_COUNT = {{ (int) config('shipmate.max_label_print_count', 5) }};

        function getShipmateSelectedStitcherName() {
            const sel = document.getElementById('shipmateStitcherSelect');
            if (!sel || !sel.value) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.textContent.trim() : '';
        }

        function updateShipmateStep2Header() {
            const nameEl = document.getElementById('shipmateStep2StitcherName');
            const countEl = document.getElementById('shipmateStep2ScanCount');
            const initialEl = document.getElementById('shipmateStep2StitcherInitial');
            const name = getShipmateSelectedStitcherName() || '';
            if (nameEl) {
                nameEl.textContent = name || '—';
            }
            if (countEl) {
                countEl.textContent = String(shipmateSessionScanCount);
            }
            if (initialEl) {
                const ch = name.length ? name.trim().charAt(0).toUpperCase() : '—';
                initialEl.textContent = ch;
            }
        }

        function shipmateEscapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function requestShipmateBack() {
            const stitcherName = getShipmateSelectedStitcherName();
            const n = shipmateSessionScanCount;
            if (n > 0) {
                const orderWord = n === 1 ? 'order' : 'orders';
                Swal.fire({
                    title: 'Finish?',
                    html:
                        '<p class="mb-2">You have printed labels for <strong>' + n + '</strong> ' + orderWord + ' for <strong>' + shipmateEscapeHtml(stitcherName) + '</strong> in this session.</p>' +
                        '<p class="mb-0 text-muted small">Are you sure you want to finish?</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, finish',
                    cancelButtonText: 'Stay',
                }).then((result) => {
                    if (result.isConfirmed) {
                        goToShipmateStitcherStep();
                    }
                });
            } else {
                Swal.fire({
                    title: 'Finish?',
                    text: 'Are you sure you want to finish?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, finish',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (result.isConfirmed) {
                        goToShipmateStitcherStep();
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('shipmateStitcherNextBtn').addEventListener('click', goToShipmateOrderStep);
            document.getElementById('shipmateFinishBtn').addEventListener('click', requestShipmateBack);
            document.getElementById('shipmateItemSelectBackBtn').addEventListener('click', shipmateBackToOrderSearchFromItemSelect);
            document.getElementById('shipmateOrderId').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    shipmateSearchOrder(document.getElementById('shipmateSearchOrderBtn'));
                }
            });

        });

        function goToShipmateOrderStep() {
            const stitcherId = getShipmateSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first.');
                return;
            }
            shipmateSessionScanCount = 0;
            shipmatePendingOrders = [];
            document.getElementById('shipmateStepStitcher').hidden = true;
            document.getElementById('shipmateStepOrder').hidden = false;
            document.getElementById('shipmateStepItemSelect').hidden = true;
            document.getElementById('shipmateResultsContainer').innerHTML = '';
            document.getElementById('shipmateItemSelectContainer').innerHTML = '';
            updateShipmateStep2Header();

            const orderInput = document.getElementById('shipmateOrderId');
            orderInput.disabled = false;
            orderInput.value = '';
            requestAnimationFrame(() => {
                orderInput.focus();
                orderInput.select();
            });
        }

        function goToShipmateStitcherStep() {
            document.getElementById('shipmateStepOrder').hidden = true;
            document.getElementById('shipmateStepItemSelect').hidden = true;
            document.getElementById('shipmateStepStitcher').hidden = false;
            document.getElementById('shipmateResultsContainer').innerHTML = '';
            document.getElementById('shipmateItemSelectContainer').innerHTML = '';
            shipmatePendingOrders = [];
            const sel = document.getElementById('shipmateStitcherSelect');
            requestAnimationFrame(() => sel.focus());
        }

        function shipmateBackToOrderSearchFromItemSelect() {
            document.getElementById('shipmateStepItemSelect').hidden = true;
            document.getElementById('shipmateItemSelectContainer').innerHTML = '';
            document.getElementById('shipmateResultsContainer').innerHTML = '';
            shipmatePendingOrders = [];
            const orderInput = document.getElementById('shipmateOrderId');
            requestAnimationFrame(() => {
                orderInput.focus();
                orderInput.select();
            });
        }

        function getShipmateSelectedStitcherId() {
            const sel = document.getElementById('shipmateStitcherSelect');
            return sel && sel.value ? sel.value : '';
        }

        function shipmateSearchOrder(element) {
            const stitcherId = getShipmateSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first (use Finish if needed).');
                return;
            }

            const resultsContainer = document.getElementById('shipmateResultsContainer');
            const orderIdInput = document.getElementById('shipmateOrderId');
            const inputValue = orderIdInput.value.trim();

            if (inputValue.length < 1) {
                toastr.error('Please enter order number.');
                return;
            }

            resultsContainer.innerHTML = '';
            document.getElementById('shipmateStepItemSelect').hidden = true;
            document.getElementById('shipmateItemSelectContainer').innerHTML = '';
            shipmatePendingOrders = [];

            element.disabled = true;
            toggleLoader(true);

            fetch(`{{ route('console.shipmate.fetchOrder', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(inputValue)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    },
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const orders = result.orders || [];
                        if (orders.length === 0) {
                            toastr.error('No orders found for this query.');
                            return;
                        }
                        shipmateHandleSearchResults(orders, resultsContainer);
                    } else {
                        toastr.error('No orders found for this query.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toastr.error('Something went wrong.');
                })
                .finally(() => {
                    orderIdInput.value = '';
                    orderIdInput.focus();
                    element.disabled = false;
                    toggleLoader(false);
                });
        }

        function shipmateHandleSearchResults(orders, resultsContainer) {
            if (orders.length === 1) {
                shipmateRenderOrders(orders, resultsContainer);
                return;
            }

            shipmatePendingOrders = orders;
            shipmateShowItemSelectStep(orders);
        }

        function shipmateShowItemSelectStep(orders) {
            const container = document.getElementById('shipmateItemSelectContainer');
            container.innerHTML = '';

            orders.forEach((order) => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-xl-4';

                const card = document.createElement('div');
                card.className = 'resultBox shipping-item-select-card h-100 p-3';
                card.tabIndex = 0;
                card.innerHTML = `
                    <div class="text-start">
                        <div class="fw-semibold mb-1">Order Number: ${shipmateEscapeHtml(String(order.order_item_id || ''))}</div>
                        <div class="small text-muted mb-2">Order ID: ${shipmateEscapeHtml(String(order.order_id || ''))}</div>
                        <div class="small mb-1"><span class="text-muted">Material:</span> ${shipmateEscapeHtml(order.material_type || '—')}</div>
                        <div class="small mb-1"><span class="text-muted">Edging:</span> ${shipmateEscapeHtml(order.edging || '—')}</div>
                        <div class="small mb-3"><span class="text-muted">Status:</span> ${shipmateEscapeHtml(order.status || '—')}</div>
                        <button type="button" class="btn btn-primary btn-sm w-100">Select this item</button>
                    </div>
                `;

                const selectItem = () => shipmateSelectOrderItem(order);
                card.addEventListener('click', selectItem);
                card.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectItem();
                    }
                });

                col.appendChild(card);
                container.appendChild(col);
            });

            document.getElementById('shipmateStepItemSelect').hidden = false;
            document.getElementById('shipmateResultsContainer').innerHTML = '';
        }

        function shipmateSelectOrderItem(order) {
            document.getElementById('shipmateStepItemSelect').hidden = true;
            document.getElementById('shipmateItemSelectContainer').innerHTML = '';
            shipmatePendingOrders = [];

            const resultsContainer = document.getElementById('shipmateResultsContainer');
            shipmateRenderOrders([order], resultsContainer);
        }

        function shipmateRenderOrders(orders, resultsContainer) {
            const fragment = document.createDocumentFragment();
            orders.forEach(order => {
                const card = shipmateCreateOrderCard(order);
                fragment.appendChild(card);
            });
            resultsContainer.innerHTML = '';
            resultsContainer.appendChild(fragment);

            const autoButtons = Array.from(resultsContainer.querySelectorAll('.shipmateGenerateAndPrintOrderLabelButton'));
            shipmateProcessLabelQueueSequentially(autoButtons);
        }

        async function shipmateProcessLabelQueueSequentially(buttons) {
            for (const button of buttons) {
                await shipmateGenerateAndPrintOrderLabel(button);
            }
        }

        function shipmateCreateOrderCard(order) {
            const qty = parseInt(order.quantity) || 0;
            const rows = [
                ['Recipient Name', order.recipient_name ?? ''],
                ['Address', order.address ?? ''],
                ['City', order.city ?? ''],
                ['Postal Code', order.postal_code ?? ''],
                ['Country', order.country ?? ''],
                ['Make & Model', order.make_model ?? ''],
                ['Material Type', order.material_type ?? ''],
                ['Edging', order.edging ?? ''],
                ['Product Code', order.product_code ?? ''],
                ['Quantity', qty]
            ];

            const wrapper = document.createElement('div');
            wrapper.className = 'col-12 mb-4';
            const box = document.createElement('div');
            box.className = 'resultBox text-center p-3 position-relative';

            const labelCount = parseInt(order.label_count, 10) || 0;
            const labelCountBadge = document.createElement('div');
            labelCountBadge.className = 'shipmate-label-count position-absolute top-0 end-0 m-3 text-end';
            labelCountBadge.innerHTML = `
                <div class="text-muted small mb-1">Labels printed</div>
                <span class="badge bg-primary fs-6 rounded-pill shipmateLabelCount">${labelCount}</span>
            `;
            box.appendChild(labelCountBadge);

            const header = document.createElement('div');
            header.className = 'row';
            header.innerHTML = `
                <div class="col-lg-12 mb-2 text-center">
                    <h1>RESULT</h1>
                </div>
                <div class="col-md-4">
                    <span class="font-weight-bold">ORDERID:</span> <span class="orderId">${order.order_id ?? ''}</span>
                </div>
                <div class="col-md-4">
                    <span class="font-weight-bold">OrderNumber:</span> <span class="orderNo">${order.order_item_id ?? ''}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-success">STATUS: </span> <span class="orderStatus font-weight-bold">${order.status ?? ''}</span>
                </div>
            `;

            const notes = document.createElement('div');
            notes.className = 'col-md-12 mt-2';
            notes.innerHTML = `
                <p class="font-weight-bold my-2 m-auto text-justify internal_parent">Internal Notes :
                    <br>
                    <span class="internalNotes my-2 text-left font-weight-light" style="white-space: break-spaces">${order.description ?? ''}</span>
                </p>
            `;

            const tableWrap = document.createElement('div');
            tableWrap.className = 'col-md-12 mt-2';
            const table = document.createElement('table');
            table.className = 'table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th scope="col">Field</th>
                        <th scope="col">Value</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(([key, value]) => `<tr><td>${key}</td><td>${value}</td></tr>`).join('')}
                </tbody>
            `;
            tableWrap.appendChild(table);

            const actions = document.createElement('div');
            actions.className = 'col-12 mt-2 d-flex justify-content-center align-items-center gap-2 flex-wrap';
            const btn = document.createElement('button');
            btn.className = 'btn btn-success shipmateGenerateAndPrintOrderLabelButton';
            btn.dataset.orderId = order.order_id;
            btn.dataset.itemId = order.order_item_id;
            btn.dataset.hasSavedLabel = order.files ? 'true' : 'false';
            btn.dataset.generatedBy = order.generated_by || '';
            btn.textContent = (order.files || order.generated_by) ? 'Print Label' : 'Create & Print Label';
            btn.disabled = false;
            btn.onclick = function() { shipmateGenerateAndPrintOrderLabel(this); };
            actions.appendChild(btn);

            const additionalBtn = document.createElement('button');
            additionalBtn.type = 'button';
            additionalBtn.className = 'btn btn-outline-primary shipmateAdditionalLabelButton';
            additionalBtn.dataset.orderId = order.order_id;
            additionalBtn.dataset.itemId = order.order_item_id;
            additionalBtn.textContent = 'Additional Label';
            additionalBtn.onclick = function() { shipmateGenerateAdditionalLabel(this); };
            actions.appendChild(additionalBtn);

            box.appendChild(header);
            box.appendChild(notes);
            box.appendChild(tableWrap);
            box.appendChild(actions);
            shipmateSyncAdditionalLabelButton(box, labelCount);
            wrapper.appendChild(box);
            return wrapper;
        }

        function shipmateRecreateConfirmHtml(generatedBy) {
            const service = (generatedBy || '').toLowerCase();
            let line = 'A label has already been generated for this order.';
            if (service === 'evri') {
                line = 'This label was already generated with <strong>Evri</strong>.';
            } else if (service === 'veeqo') {
                line = 'This label was already generated with <strong>Veeqo</strong>.';
            }
            return line + '<p class="mb-0 mt-2 text-muted small">Do you want to recreate a label from Shipmate?</p>';
        }

        function shipmateRequiresRecreateConfirm(hasSavedLabel, generatedBy) {
            if (!hasSavedLabel) {
                return false;
            }
            return (generatedBy || '').toLowerCase() !== 'shipmate';
        }

        async function shipmateConfirmRecreateIfNeeded(element) {
            const hasSavedLabel = element.dataset.hasSavedLabel === 'true';
            const generatedBy = (element.dataset.generatedBy || '').toLowerCase();

            if (!shipmateRequiresRecreateConfirm(hasSavedLabel, generatedBy)) {
                return true;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Label already exists',
                html: shipmateRecreateConfirmHtml(generatedBy),
                showCancelButton: true,
                confirmButtonText: 'Yes, recreate with Shipmate',
                cancelButtonText: 'Cancel',
            });

            return result.isConfirmed;
        }

        function shipmateUpdateLabelCountOnCard(element, count) {
            const card = element?.closest?.('.resultBox');
            const badge = card?.querySelector('.shipmateLabelCount');
            if (badge) {
                badge.textContent = String(parseInt(count, 10) || 0);
            }
            shipmateSyncAdditionalLabelButton(card, count);
        }

        function shipmateSyncAdditionalLabelButton(cardOrElement, count) {
            const card = cardOrElement?.classList?.contains?.('resultBox')
                ? cardOrElement
                : cardOrElement?.closest?.('.resultBox');
            if (!card) return;

            const n = parseInt(count, 10) || 0;
            const btn = card.querySelector('.shipmateAdditionalLabelButton');
            if (!btn) return;

            const atLimit = n >= SHIPMATE_MAX_LABEL_PRINT_COUNT;
            btn.disabled = atLimit;
            btn.title = atLimit
                ? 'Maximum additional labels (' + SHIPMATE_MAX_LABEL_PRINT_COUNT + ') reached'
                : '';
        }

        function shipmatePrintLabelPdf(fileUrl) {
            const objFra = document.createElement('iframe');
            objFra.style.visibility = 'hidden';
            objFra.src = fileUrl;
            document.body.appendChild(objFra);
            objFra.onload = () => {
                objFra.contentWindow.focus();
                objFra.contentWindow.print();
            };
        }

        async function shipmateGenerateAdditionalLabel(element) {
            const orderId = element.dataset.orderId;
            const itemId = element.dataset.itemId;
            if (!orderId) return false;

            const card = element.closest('.resultBox');
            const currentCount = parseInt(card?.querySelector('.shipmateLabelCount')?.textContent, 10) || 0;
            if (currentCount >= SHIPMATE_MAX_LABEL_PRINT_COUNT) {
                toastr.warning('Maximum additional labels (' + SHIPMATE_MAX_LABEL_PRINT_COUNT + ') reached.');
                shipmateSyncAdditionalLabelButton(card, currentCount);
                return false;
            }

            element.disabled = true;
            toggleLoader(true);
            try {
                const response = await fetch(`{{ route('console.shipmate.generateAdditionalLabel', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(orderId)), {
                    method: 'POST',
                    body: JSON.stringify({ itemId: itemId }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    toastr.error(response.status === 403 ? 'You do not have permission to perform this action.' : 'Request failed. Please try again.');
                    return false;
                }

                const result = await response.json();
                if (result.success) {
                    if (result.label_count !== undefined) {
                        shipmateUpdateLabelCountOnCard(element, result.label_count);
                    }
                    toastr.success('Additional label created.' + (result.tracking_number ? ' Tracking: ' + result.tracking_number : ''));
                    shipmatePrintLabelPdf(result.fileUrl);
                    return true;
                }

                toastr.error(result.error || 'Failed to create additional label.');
                if (result.label_count !== undefined) {
                    shipmateUpdateLabelCountOnCard(element, result.label_count);
                }
                return false;
            } catch (error) {
                console.error('Error:', error);
                toastr.error('Something went wrong.');
                return false;
            } finally {
                const card = element.closest('.resultBox');
                const n = parseInt(card?.querySelector('.shipmateLabelCount')?.textContent, 10) || 0;
                shipmateSyncAdditionalLabelButton(card, n);
                if (n < SHIPMATE_MAX_LABEL_PRINT_COUNT) {
                    element.disabled = false;
                }
                toggleLoader(false);
            }
        }

        async function shipmateGenerateAndPrintOrderLabel(element) {
            const stitcherId = getShipmateSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first.');
                return false;
            }

            const orderId = element.dataset.orderId;
            const itemId = element.dataset.itemId;
            if (!orderId) return false;

            const hasSavedLabel = element.dataset.hasSavedLabel === 'true';
            const generatedBy = (element.dataset.generatedBy || '').toLowerCase();
            const needsRecreateConfirm = shipmateRequiresRecreateConfirm(hasSavedLabel, generatedBy);

            if (needsRecreateConfirm) {
                const confirmed = await shipmateConfirmRecreateIfNeeded(element);
                if (!confirmed) {
                    return false;
                }
            }

            const forceShipmate = needsRecreateConfirm;

            element.disabled = true;
            element.textContent = 'Print Label';
            toggleLoader(true);
            try {
                const response = await fetch(`{{ route('console.shipmate.generateAndPrintOrderLabel', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(orderId)), {
                    method: 'POST',
                    body: JSON.stringify({
                        itemId: itemId,
                        stitcher_id: stitcherId,
                        force_shipmate: forceShipmate,
                    }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                    },
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    toastr.error(response.status === 403 ? 'You do not have permission to perform this action.' : 'Request failed. Please try again.');
                    return false;
                }

                const result = await response.json();

                if (result.success) {
                    if (result.label_count !== undefined) {
                        shipmateUpdateLabelCountOnCard(element, result.label_count);
                    }
                    const card = element.closest('.resultBox');
                    if (card) {
                        const statusEl = card.querySelector('.orderStatus');
                        if (statusEl) statusEl.textContent = 'shipped';
                    }
                    element.dataset.hasSavedLabel = 'true';
                    element.dataset.generatedBy = 'shipmate';

                    if (result.reused_existing_label) {
                        const provider = result.generated_by ? String(result.generated_by).toUpperCase() : 'EXISTING';
                        toastr.info('Using saved label (' + provider + ').');
                    } else if (forceShipmate) {
                        toastr.success('New Shipmate label created.');
                    }
                    if (result.already_assigned) {
                        toastr.warning(result.message || 'This order is already assigned to a stitcher.');
                    }
                    if (result.assignment_applied) {
                        shipmateSessionScanCount++;
                        updateShipmateStep2Header();
                    }
                    shipmatePrintLabelPdf(result.fileUrl);
                    return true;
                }

                toastr.error(result.error || 'Failed to create and print order.');
                return false;
            } catch (error) {
                console.error('Error:', error);
                toastr.error('Something went wrong.');
                return false;
            } finally {
                element.disabled = false;
                toggleLoader(false);
            }
        }
    </script>
@endpush
