@extends('console.layout.app')

@section('title', 'Label Printing')

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

        .evri-label-count {
            z-index: 1;
            min-width: 4.5rem;
        }

        .evri-label-count .evriLabelCount {
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
        <div id="stepStitcher" class="row g-3 shipping-step-panel">
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 1 of 3</p>
                <label for="stitcherSelect" class="form-label fw-semibold">Stitcher</label>
                <select id="stitcherSelect" class="form-select" required>
                    <option value="">— Select stitcher —</option>
                    @foreach ($stitchers as $stitcher)
                        <option value="{{ $stitcher->id }}">{{ $stitcher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-12 text-center">
                <button type="button" id="stitcherNextBtn" class="btn btn-primary mt-2">
                    Next
                </button>
            </div>
        </div>

        <div id="stepOrder" class="row g-3 shipping-step-panel" hidden>
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 2 of 3</p>
                <div class="shipping-session-banner rounded-3 p-3 shadow-sm mb-3">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-md min-w-0">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 44px; height: 44px; font-size: 1.1rem; font-weight: 600;"
                                     id="shippingStep2StitcherInitial"
                                     aria-hidden="true">—</div>
                                <div class="min-w-0">
                                    <div class="text-uppercase text-muted small mb-0" style="letter-spacing: 0.06em;">Printing as</div>
                                    <div id="shippingStep2StitcherName" class="fs-5 fw-semibold text-dark text-truncate">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-auto text-center">
                            <button type="button" id="shippingFinishBtn" class="btn btn-success px-4 py-2 fw-semibold">
                                Finish
                            </button>
                        </div>
                        <div class="col-12 col-md">
                            <div class="d-flex align-items-center justify-content-center justify-content-md-end gap-2">
                                <div class="vr d-none d-md-block align-self-stretch my-1 opacity-50"></div>
                                <span class="text-muted small mb-0 d-none d-md-inline">Number of Orders</span>
                                <span class="text-muted small mb-0 d-md-none">Orders</span>
                                <span id="shippingStep2ScanCount" class="badge bg-primary scan-pill fs-6 px-3 py-2 rounded-pill">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                <label for="orderId" class="form-label fw-semibold">Order number</label>
                <input type="text" id="orderId" class="form-control" placeholder="Scan or type order number">
            </div>
            <div class="col-lg-12 text-center">
                <button type="button" id="searchOrderBtn" class="btn btn-primary mt-2" onclick="searchOrder(this)">
                    SEARCH NOW
                </button>
            </div>
        </div>

        <div id="stepItemSelect" class="row g-3 shipping-step-panel my-4" hidden>
            <div class="col-lg-12">
                <p class="text-muted small mb-2">Step 3 of 3</p>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Select an item to print</h5>
                        <p class="text-muted small mb-0">This order has multiple items. Choose one to continue.</p>
                    </div>
                    <button type="button" id="itemSelectBackBtn" class="btn btn-outline-secondary btn-sm">
                        Back to search
                    </button>
                </div>
                <div id="itemSelectContainer" class="row g-3"></div>
            </div>
        </div>

        <div id="resultsContainer" class="row my-5"></div>
    </div>
@endsection

@push('scripts')
    <script>
        let shippingLabelsSessionScanCount = 0;
        let shippingPendingOrders = [];
        const EVRI_MAX_LABEL_PRINT_COUNT = {{ (int) config('evri.max_label_print_count', 5) }};

        function getSelectedStitcherName() {
            const sel = document.getElementById('stitcherSelect');
            if (!sel || !sel.value) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.textContent.trim() : '';
        }

        function updateShippingStep2Header() {
            const nameEl = document.getElementById('shippingStep2StitcherName');
            const countEl = document.getElementById('shippingStep2ScanCount');
            const initialEl = document.getElementById('shippingStep2StitcherInitial');
            const name = getSelectedStitcherName() || '';
            if (nameEl) {
                nameEl.textContent = name || '—';
            }
            if (countEl) {
                countEl.textContent = String(shippingLabelsSessionScanCount);
            }
            if (initialEl) {
                const ch = name.length ? name.trim().charAt(0).toUpperCase() : '—';
                initialEl.textContent = ch;
            }
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function requestShippingBack() {
            const stitcherName = getSelectedStitcherName();
            const n = shippingLabelsSessionScanCount;
            if (n > 0) {
                const orderWord = n === 1 ? 'order' : 'orders';
                Swal.fire({
                    title: 'Finish?',
                    html:
                        '<p class="mb-2">You have printed labels for <strong>' + n + '</strong> ' + orderWord + ' for <strong>' + escapeHtml(stitcherName) + '</strong> in this session.</p>' +
                        '<p class="mb-0 text-muted small">Are you sure you want to finish?</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, finish',
                    cancelButtonText: 'Stay',
                }).then((result) => {
                    if (result.isConfirmed) {
                        goToStitcherStep();
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
                        goToStitcherStep();
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('stitcherNextBtn').addEventListener('click', goToOrderStep);
            document.getElementById('shippingFinishBtn').addEventListener('click', requestShippingBack);
            document.getElementById('itemSelectBackBtn').addEventListener('click', backToOrderSearchFromItemSelect);
            document.getElementById('orderId').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchOrder(document.getElementById('searchOrderBtn'));
                }
            });
        });

        function goToOrderStep() {
            const stitcherId = getSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first.');
                return;
            }
            shippingLabelsSessionScanCount = 0;
            shippingPendingOrders = [];
            document.getElementById('stepStitcher').hidden = true;
            document.getElementById('stepOrder').hidden = false;
            document.getElementById('stepItemSelect').hidden = true;
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('itemSelectContainer').innerHTML = '';
            updateShippingStep2Header();

            const orderInput = document.getElementById('orderId');
            orderInput.disabled = false;
            orderInput.value = '';
            requestAnimationFrame(() => {
                orderInput.focus();
                orderInput.select();
            });
        }

        function goToStitcherStep() {
            document.getElementById('stepOrder').hidden = true;
            document.getElementById('stepItemSelect').hidden = true;
            document.getElementById('stepStitcher').hidden = false;
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('itemSelectContainer').innerHTML = '';
            shippingPendingOrders = [];
            const sel = document.getElementById('stitcherSelect');
            requestAnimationFrame(() => sel.focus());
        }

        function backToOrderSearchFromItemSelect() {
            document.getElementById('stepItemSelect').hidden = true;
            document.getElementById('itemSelectContainer').innerHTML = '';
            document.getElementById('resultsContainer').innerHTML = '';
            shippingPendingOrders = [];
            const orderInput = document.getElementById('orderId');
            requestAnimationFrame(() => {
                orderInput.focus();
                orderInput.select();
            });
        }

        function getSelectedStitcherId() {
            const sel = document.getElementById('stitcherSelect');
            return sel && sel.value ? sel.value : '';
        }

        function searchOrder(element) {
            const stitcherId = getSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first (use Back if needed).');
                return;
            }

            const resultsContainer = document.getElementById('resultsContainer');
            const orderIdInput = document.getElementById('orderId');
            const inputValue = orderIdInput.value.trim();

            if (inputValue.length < 1) {
                toastr.error('Please enter order number.');
                return;
            }

            resultsContainer.innerHTML = '';
            document.getElementById('stepItemSelect').hidden = true;
            document.getElementById('itemSelectContainer').innerHTML = '';
            shippingPendingOrders = [];

            element.disabled = true;
            toggleLoader(true);

            fetch(`{{ route('console.shippingLabels.fetchOrder', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(inputValue)), {
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
                       
                    // const hasPrime = orders.some(order => order.source === "Amazon_Prime");

                    // if (hasPrime) {
                    //     toastr.error('This is a prime order.');
                    //     return;
                    // }

                    handleSearchResults(orders, resultsContainer);

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

        function handleSearchResults(orders, resultsContainer) {
            if (orders.length === 1) {
                renderOrders(orders, resultsContainer);
                return;
            }

            shippingPendingOrders = orders;
            showItemSelectStep(orders);
        }

        function showItemSelectStep(orders) {
            const container = document.getElementById('itemSelectContainer');
            container.innerHTML = '';

            orders.forEach((order) => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-xl-4';

                const card = document.createElement('div');
                card.className = 'resultBox shipping-item-select-card h-100 p-3';
                card.tabIndex = 0;
                card.innerHTML = `
                    <div class="text-start">
                        <div class="fw-semibold mb-1">Order Number: ${escapeHtml(String(order.order_item_id || ''))}</div>
                        <div class="small text-muted mb-2">Order ID: ${escapeHtml(String(order.order_id || ''))}</div>
                        <div class="small mb-1"><span class="text-muted">Material:</span> ${escapeHtml(order.material_type || '—')}</div>
                        <div class="small mb-1"><span class="text-muted">Edging:</span> ${escapeHtml(order.edging || '—')}</div>
                        <div class="small mb-3"><span class="text-muted">Status:</span> ${escapeHtml(order.status || '—')}</div>
                        <button type="button" class="btn btn-primary btn-sm w-100">Select this item</button>
                    </div>
                `;

                const selectItem = () => selectOrderItem(order);
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

            document.getElementById('stepItemSelect').hidden = false;
            document.getElementById('resultsContainer').innerHTML = '';
        }

        function selectOrderItem(order) {
            document.getElementById('stepItemSelect').hidden = true;
            document.getElementById('itemSelectContainer').innerHTML = '';
            shippingPendingOrders = [];

            const resultsContainer = document.getElementById('resultsContainer');
            renderOrders([order], resultsContainer);
        }

        function renderOrders(orders, resultsContainer) {
            const fragment = document.createDocumentFragment();
            orders.forEach(order => {
                const card = createOrderCard(order);
                fragment.appendChild(card);
            });
            resultsContainer.innerHTML = '';
            resultsContainer.appendChild(fragment);

            // Process labels one-by-one to avoid duplicate simultaneous provider calls
            // for multi-item orders (especially Amazon Prime/Veeqo).
            const autoButtons = Array.from(resultsContainer.querySelectorAll('.generateAndPrintOrderLabelButton'));
            processLabelQueueSequentially(autoButtons);
        }

        async function processLabelQueueSequentially(buttons) {
            for (const button of buttons) {
                await generateAndPrintOrderLabel(button);
            }
        }

        function createOrderCard(order) {
            const qty = parseInt(order.quantity) || 0;
            const rows = [
                ['Recipient Name', order.recipient_name ?? ''],
                ['Address', order.address ?? ''],
                ['City', order.city ?? ''],
                ['Postal Code', order.postal_code ?? ''],
                ['Make & Model', order.make_model ?? ''],
                ['Material Type', order.material_type ?? ''],
                ['Edging', order.edging ?? ''],
                ['Product Code', order.product_code ?? ''],
                ['Quantity', qty]
            ];

            const isPrime = order.source === 'Amazon_Prime';
            const labelCount = parseInt(order.label_count, 10) || 0;

            const wrapper = document.createElement('div');
            wrapper.className = 'col-12 mb-4';
            const box = document.createElement('div');
            box.className = 'resultBox text-center p-3 position-relative';

            if (!isPrime) {
                const labelCountBadge = document.createElement('div');
                labelCountBadge.className = 'evri-label-count position-absolute top-0 end-0 m-3 text-end';
                labelCountBadge.innerHTML = `
                    <div class="text-muted small mb-1">Labels printed</div>
                    <span class="badge bg-primary fs-6 rounded-pill evriLabelCount">${labelCount}</span>
                `;
                box.appendChild(labelCountBadge);
            }

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
            btn.className = 'btn btn-success generateAndPrintOrderLabelButton';
            btn.dataset.orderId = order.order_id;
            btn.dataset.itemId = order.order_item_id;
            btn.dataset.prime = isPrime ? 'true' : 'false';
            btn.dataset.hasSavedLabel = order.files ? 'true' : 'false';
            btn.dataset.generatedBy = order.generated_by || '';
            btn.textContent = (order.files || order.generated_by) ? 'Print Label' : 'Create & Print Label';
            btn.disabled = false;
            btn.onclick = function() { generateAndPrintOrderLabel(this); };
            actions.appendChild(btn);

            if (!isPrime) {
                const additionalBtn = document.createElement('button');
                additionalBtn.type = 'button';
                additionalBtn.className = 'btn btn-outline-primary evriAdditionalLabelButton';
                additionalBtn.dataset.orderId = order.order_id;
                additionalBtn.dataset.itemId = order.order_item_id;
                additionalBtn.textContent = 'Additional Label';
                additionalBtn.onclick = function() { generateAdditionalEvriLabel(this); };
                actions.appendChild(additionalBtn);
            }

            box.appendChild(header);
            box.appendChild(notes);
            box.appendChild(tableWrap);
            box.appendChild(actions);
            if (!isPrime) {
                syncEvriAdditionalLabelButton(box, labelCount);
            }
            wrapper.appendChild(box);
            return wrapper;
        }

        function updateEvriLabelCountOnCard(element, count) {
            const card = element?.closest?.('.resultBox');
            const badge = card?.querySelector('.evriLabelCount');
            if (badge) {
                badge.textContent = String(parseInt(count, 10) || 0);
            }
            syncEvriAdditionalLabelButton(card, count);
        }

        function syncEvriAdditionalLabelButton(cardOrElement, count) {
            const card = cardOrElement?.classList?.contains?.('resultBox')
                ? cardOrElement
                : cardOrElement?.closest?.('.resultBox');
            if (!card) return;

            const n = parseInt(count, 10) || 0;
            const btn = card.querySelector('.evriAdditionalLabelButton');
            if (!btn) return;

            const atLimit = n >= EVRI_MAX_LABEL_PRINT_COUNT;
            btn.disabled = atLimit;
            btn.title = atLimit
                ? 'Maximum additional labels (' + EVRI_MAX_LABEL_PRINT_COUNT + ') reached'
                : '';
        }

        function printLabelPdf(fileUrl) {
            const objFra = document.createElement('iframe');
            objFra.style.visibility = 'hidden';
            objFra.src = fileUrl;
            document.body.appendChild(objFra);
            objFra.onload = () => {
                objFra.contentWindow.focus();
                objFra.contentWindow.print();
            };
        }

        async function generateAdditionalEvriLabel(element) {
            const orderId = element.dataset.orderId;
            const itemId = element.dataset.itemId;
            if (!orderId) return false;

            const card = element.closest('.resultBox');
            const currentCount = parseInt(card?.querySelector('.evriLabelCount')?.textContent, 10) || 0;
            if (currentCount >= EVRI_MAX_LABEL_PRINT_COUNT) {
                toastr.warning('Maximum additional labels (' + EVRI_MAX_LABEL_PRINT_COUNT + ') reached.');
                syncEvriAdditionalLabelButton(card, currentCount);
                return false;
            }

            element.disabled = true;
            toggleLoader(true);
            try {
                const response = await fetch(`{{ route('console.shippingLabels.generateAdditionalLabel', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(orderId)), {
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
                        updateEvriLabelCountOnCard(element, result.label_count);
                    }
                    toastr.success('Additional label created.' + (result.tracking_number ? ' Tracking: ' + result.tracking_number : ''));
                    printLabelPdf(result.fileUrl);
                    return true;
                }

                toastr.error(result.error || 'Failed to create additional label.');
                if (result.label_count !== undefined) {
                    updateEvriLabelCountOnCard(element, result.label_count);
                }
                return false;
            } catch (error) {
                console.error('Error:', error);
                toastr.error('Something went wrong.');
                return false;
            } finally {
                const card = element.closest('.resultBox');
                const n = parseInt(card?.querySelector('.evriLabelCount')?.textContent, 10) || 0;
                syncEvriAdditionalLabelButton(card, n);
                if (n < EVRI_MAX_LABEL_PRINT_COUNT) {
                    element.disabled = false;
                }
                toggleLoader(false);
            }
        }

        async function generateAndPrintOrderLabel(element) {
            const stitcherId = getSelectedStitcherId();
            if (!stitcherId) {
                toastr.error('Please select a stitcher first.');
                return false;
            }

            const orderId = element.dataset.orderId;
            const itemId = element.dataset.itemId;
            const isPrime = element.dataset.prime === 'true';
            if (!orderId) return false;
            element.disabled = true;
            element.textContent = 'Print Label';
            toggleLoader(true);
            try {
                const response = await fetch(`{{ route('console.shippingLabels.generateAndPrintOrderLabel', ['id' => '__ORDER_ID__']) }}`.replace('__ORDER_ID__', encodeURIComponent(orderId)), {
                    method: 'POST',
                    body: JSON.stringify({ itemId: itemId, stitcher_id: stitcherId, is_prime: isPrime }),
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
                        updateEvriLabelCountOnCard(element, result.label_count);
                    }
                    const card = element.closest('.resultBox');
                    if (card) {
                        const statusEl = card.querySelector('.orderStatus');
                        if (statusEl) statusEl.textContent = 'shipped';
                    }
                    element.dataset.hasSavedLabel = 'true';
                    if (!isPrime) {
                        element.dataset.generatedBy = 'evri';
                    }

                    if (result.reused_existing_label) {
                        const provider = result.generated_by ? String(result.generated_by).toUpperCase() : 'EXISTING';
                        toastr.info('Using saved label (' + provider + ').');
                    }
                    if (result.already_assigned) {
                        toastr.warning(result.message || 'This order is already assigned to a stitcher.');
                    }
                    if (result.assignment_applied) {
                        shippingLabelsSessionScanCount++;
                        updateShippingStep2Header();
                    }
                    printLabelPdf(result.fileUrl);
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
