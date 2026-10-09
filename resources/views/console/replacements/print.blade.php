@extends('console.layout.app')

@section('title', 'Print Labels')

@push('styles')
    <style>
        .replacement-print-page {
            min-height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .replacement-print-header,
        .replacement-print-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #dee2e6;
            background: #fff;
        }

        .replacement-print-footer {
            border-bottom: 0;
            border-top: 1px solid #dee2e6;
            margin-top: auto;
        }

        .replacement-print-body {
            flex: 1 1 auto;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding-top: 20px;
            min-height: calc(100vh - 220px);
        }

        .label-card {
            border: none;
            box-shadow: none;
            max-width: 100%;
            margin: 0 auto;
        }

        .label-card .card-body {
            padding: 0.5rem 0 1rem;
        }

        .production-label {
            --pl-border: #111;
            --pl-font: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
            --pl-outer-gap: 0.08in;
            width: calc(4in - (2 * var(--pl-outer-gap)));
            height: calc(6in - (2 * var(--pl-outer-gap)));
            max-width: calc(100% - 0.16in);
            margin: var(--pl-outer-gap) auto;
            color: #111;
            background: #fff;
            font-family: var(--pl-font);
            box-sizing: border-box;
            border: 2px solid var(--pl-border);
            border-radius: 10px;
            padding: 0.07in;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .production-label *,
        .production-label *::before,
        .production-label *::after {
            box-sizing: border-box;
        }

        .pl-section {
            border: 1.5px solid var(--pl-border);
            border-radius: 6px;
            margin-bottom: 0.08in;
            overflow: hidden;
            background: #fff;
            flex: 0 0 auto;
        }

        .pl-section:last-child {
            margin-bottom: 0;
            flex: 1 1 auto;
        }

        .pl-codes {
            display: flex;
            align-items: stretch;
            min-height: 0.95in;
            padding: 0.06in 0.08in;
            gap: 0;
            border: none;
            border-radius: 0;
        }

        .pl-barcode {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 0 10px 0 4px;
            min-width: 0;
        }

        .pl-barcode-img {
            width: 100%;
            max-height: 66px;
            object-fit: contain;
            display: block;
        }

        .pl-barcode-text {
            margin-top: 3px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-align: center;
            word-break: break-all;
        }

        .pl-qr {
            flex: 0 0 74px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: 1.5px solid var(--pl-border);
            padding-left: 8px;
        }

        .pl-qr-img {
            width: 76px;
            height: 90px;
            object-fit: contain;
            display: block;
        }

        .pl-product {
            display: flex;
            gap: 8px;
            padding: 8px 10px;
            align-items: stretch;
            margin-bottom: 0.06in;
        }

        .pl-product-main {
            flex: 1 1 auto;
            min-width: 0;
            text-align: left;
        }

        .pl-title {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.1;
            text-transform: uppercase;
            letter-spacing: 0.01em;
        }

        .pl-attrs {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .pl-badges {
            flex: 0 0 78px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .pl-badge {
            border: 1.5px solid var(--pl-border);
            border-radius: 4px;
            min-height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 4px;
            line-height: 1.1;
            word-break: break-word;
        }

        .pl-badge-fill {
            background: #111;
            color: #fff;
            border-color: #111;
        }

        .pl-badge-qty {
            font-size: 22px;
            font-weight: 900;
            min-height: 34px;
        }

        .pl-order {
            position: relative;
            padding: 22px 10px 8px;
            min-height: 0;
            overflow: visible;
        }

        .pl-tab {
            position: absolute;
            top: 8px;
            left: 0;
            transform: translateY(-50%);
            background: #111;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.04em;
            padding: 2px 7px;
            border-radius: 2px;
            text-transform: uppercase;
        }

        .pl-order-body {
            display: block;
        }

        .pl-order-main {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
            text-transform: uppercase;
            text-align: left;
        }

        .pl-order-label {
            font-weight: 800;
        }

        .pl-order-source,
        .pl-order-company {
            font-weight: 800;
            margin-top: 1px;
        }

        .pl-order-company {
            margin-top: 4px;
        }

        .pl-note {
            min-height: 0.72in;
            padding: 0.07in 0.1in 0.09in;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .pl-note-header {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: left;
            margin-bottom: 4px;
        }

        .pl-note-body {
            flex: 1 1 auto;
            min-height: 0.38in;
        }

        .label-navigation {
            text-align: center;
            margin-top: 12px;
        }

        .prev-label-btn,
        .next-label-btn {
            background-color: white;
            border: 2px solid #0d6efd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card replacement-print-page border-0 shadow-sm">
            <div class="replacement-print-header">
                <h5 class="mb-0">Print Labels</h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm print-single-label" data-order-id="">
                        <i class="ti ti-printer me-1"></i>
                        Print
                    </button>
                    <a href="{{ route('console.replacements.index') }}" class="btn btn-secondary btn-sm">Close</a>
                </div>
            </div>

            <div class="replacement-print-body" id="labelContainer">
                <button type="button" class="btn btn-outline-primary btn-lg prev-label-btn position-absolute"
                        style="left: 20px; top: 50%; transform: translateY(-50%); z-index: 10;">
                    &larr;
                </button>

                <button type="button" class="btn btn-outline-primary btn-lg next-label-btn position-absolute"
                        style="right: 20px; top: 50%; transform: translateY(-50%); z-index: 10;">
                    &rarr;
                </button>

                <div id="labelCardsWrapper" class="w-100">
                    @foreach ($orders as $index => $order)
                        <div class="label-card-container {{ $index === 0 ? 'active' : 'd-none' }}"
                             data-index="{{ $index }}"
                             data-order-id="{{ $order->order_id }}"
                             data-listing-id="{{ $order->id }}">
                            <div class="card label-card">
                                <div class="card-body">
                                    @include('console.batch-management.completed.partials.production-label', [
                                        'order' => $order,
                                        'productSetting' => $order->product_setting,
                                    ])
                                    <div class="label-navigation">
                                        <span class="fs-5">Label {{ $index + 1 }} of {{ $orders->count() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="replacement-print-footer">
                <div></div>
                <div class="d-flex gap-2">
                    <a href="{{ route('console.replacements.index') }}" class="btn btn-secondary">Close</a>
                    <button type="button" class="btn btn-primary" onclick="printAllLabels()">
                        <i class="ti ti-printer me-1"></i>
                        Print All Labels
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        var currentLabelIndex = 0;

        function getOrderedLabelCards() {
            const wrapper = document.getElementById('labelCardsWrapper');
            if (!wrapper) {
                return [];
            }

            return Array.from(wrapper.querySelectorAll('.label-card-container'));
        }

        function getTotalLabels() {
            return getOrderedLabelCards().length;
        }

        function showLabel(index) {
            const cards = getOrderedLabelCards();
            if (!cards.length) {
                return;
            }

            const targetIndex = Math.max(0, Math.min(index, cards.length - 1));

            cards.forEach(function(card, cardIndex) {
                card.classList.remove('active');
                card.classList.add('d-none');
                card.dataset.index = String(cardIndex);

                const navigationLabel = card.querySelector('.label-navigation span');
                if (navigationLabel) {
                    navigationLabel.textContent = 'Label ' + (cardIndex + 1) + ' of ' + cards.length;
                }
            });

            cards[targetIndex].classList.remove('d-none');
            cards[targetIndex].classList.add('active');
            currentLabelIndex = targetIndex;

            updateNavigationButtons();

            const currentOrderId = cards[targetIndex].dataset.orderId;
            $('.print-single-label').data('order-id', currentOrderId);
        }

        function updateNavigationButtons() {
            const totalLabels = getTotalLabels();
            $('.prev-label-btn').prop('disabled', currentLabelIndex === 0);
            $('.next-label-btn').prop('disabled', currentLabelIndex >= totalLabels - 1);
        }

        function getProductionLabelPrintStyles() {
            return `
                @page {
                    size: 4in 6in;
                    margin: 0;
                }
                html, body {
                    margin: 0;
                    padding: 0;
                    width: 4in;
                    background: #fff;
                    color: #111;
                }
                body {
                    box-sizing: border-box;
                    font-family: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                .label-page {
                    width: 4in;
                    height: 6in;
                    box-sizing: border-box;
                    padding: 0.08in;
                    page-break-after: always;
                    break-after: page;
                    overflow: hidden;
                }
                .label-page:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
                body > .production-label {
                    width: calc(4in - 0.16in);
                    height: calc(6in - 0.16in);
                    margin: 0.08in;
                }
                .production-label {
                    --pl-border: #111;
                    width: 100%;
                    height: 100%;
                    max-width: none;
                    margin: 0;
                    color: #111;
                    background: #fff;
                    font-family: "Arial Narrow", "Helvetica Condensed", Impact, "Arial Black", Arial, sans-serif;
                    box-sizing: border-box;
                    border: 2px solid #111;
                    border-radius: 10px;
                    padding: 0.07in;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                .production-label *,
                .production-label *::before,
                .production-label *::after {
                    box-sizing: border-box;
                }
                .pl-section {
                    border: 1.5px solid #111;
                    border-radius: 6px;
                    margin-bottom: 0.08in;
                    overflow: hidden;
                    background: #fff;
                    flex: 0 0 auto;
                }
                .pl-section:last-child {
                    margin-bottom: 0;
                    flex: 1 1 auto;
                }
                .pl-codes {
                    display: flex;
                    align-items: stretch;
                    min-height: 0.95in;
                    padding: 0.06in 0.08in;
                    gap: 0;
                    border: none;
                    border-radius: 0;
                }
                .pl-barcode {
                    flex: 1 1 auto;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    padding: 0 10px 0 4px;
                    min-width: 0;
                }
                .pl-barcode-img {
                    width: 100%;
                    max-height: 66px;
                    object-fit: contain;
                    display: block;
                }
                .pl-barcode-text {
                    margin-top: 3px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                    text-align: center;
                    word-break: break-all;
                }
                .pl-qr {
                    flex: 0 0 74px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-left: 1.5px solid #111;
                    padding-left: 8px;
                }
                .pl-qr-img {
                    width: 76px;
                    height: 90px;
                    object-fit: contain;
                    display: block;
                }
                .pl-product {
                    display: flex;
                    gap: 8px;
                    padding: 8px 10px;
                    align-items: stretch;
                    margin-bottom: 0.06in;
                }
                .pl-product-main {
                    flex: 1 1 auto;
                    min-width: 0;
                    text-align: left;
                }
                .pl-title {
                    font-size: 22px;
                    font-weight: 900;
                    line-height: 1.1;
                    text-transform: uppercase;
                    letter-spacing: 0.01em;
                }
                .pl-attrs {
                    margin-top: 6px;
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.3;
                    text-transform: uppercase;
                }
                .pl-badges {
                    flex: 0 0 78px;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .pl-badge {
                    border: 1.5px solid #111;
                    border-radius: 4px;
                    min-height: 28px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    font-size: 11px;
                    font-weight: 800;
                    text-transform: uppercase;
                    padding: 3px 4px;
                    line-height: 1.1;
                    word-break: break-word;
                }
                .pl-badge-fill {
                    background: #111 !important;
                    color: #fff !important;
                    border-color: #111 !important;
                }
                .pl-badge-qty {
                    font-size: 22px;
                    font-weight: 900;
                    min-height: 34px;
                }
                .pl-order {
                    position: relative;
                    padding: 22px 10px 8px;
                    min-height: 0;
                    overflow: visible;
                    margin-top: 0.05in;
                }
                .pl-tab {
                    position: absolute;
                    top: 8px;
                    left: 0;
                    transform: translateY(-50%);
                    background: #111 !important;
                    color: #fff !important;
                    font-size: 9px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    padding: 2px 7px;
                    border-radius: 2px;
                    text-transform: uppercase;
                }
                .pl-order-body {
                    display: block;
                }
                .pl-order-main {
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.35;
                    text-transform: uppercase;
                    text-align: left;
                }
                .pl-order-label {
                    font-weight: 800;
                }
                .pl-order-source,
                .pl-order-company {
                    font-weight: 800;
                    margin-top: 1px;
                }
                .pl-order-company {
                    margin-top: 4px;
                }
                .pl-note {
                    min-height: 0.72in;
                    padding: 0.07in 0.1in 0.09in;
                    display: flex;
                    flex-direction: column;
                    flex-shrink: 0;
                }
                .pl-note-header {
                    font-size: 11px;
                    font-weight: 800;
                    text-transform: uppercase;
                    text-align: left;
                    margin-bottom: 4px;
                }
                .pl-note-body {
                    flex: 1 1 auto;
                    min-height: 0.38in;
                }
            `;
        }

        function openLabelPrintWindow(title, bodyHtml) {
            var printWindow = window.open('', '_blank');
            if (!printWindow) {
                return;
            }

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

            var triggerPrint = function() {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };

            if (printWindow.document.readyState === 'complete') {
                setTimeout(triggerPrint, 250);
            } else {
                printWindow.onload = function() {
                    setTimeout(triggerPrint, 250);
                };
            }
        }

        function printSingleLabel(orderId) {
            var labelShell = document.querySelector('.label-card-container.active .production-label');
            var labelHtml = labelShell ? labelShell.outerHTML : '';
            openLabelPrintWindow('Print Label - ' + orderId, labelHtml);
        }

        function printAllLabels() {
            var bodyHtml = '';

            getOrderedLabelCards().forEach(function(card) {
                var labelShell = card.querySelector('.production-label');
                if (!labelShell) {
                    return;
                }

                bodyHtml += '<div class="label-page">' + labelShell.outerHTML + '</div>';
            });

            openLabelPrintWindow('Print All Labels', bodyHtml);
        }

        $(document).ready(function() {
            showLabel(0);

            $('.print-single-label').click(function() {
                var orderId = $(this).data('order-id');
                printSingleLabel(orderId);
            });

            $(document).on('click', '.next-label-btn', function() {
                if (currentLabelIndex < getTotalLabels() - 1) {
                    showLabel(currentLabelIndex + 1);
                }
            });

            $(document).on('click', '.prev-label-btn', function() {
                if (currentLabelIndex > 0) {
                    showLabel(currentLabelIndex - 1);
                }
            });
        });
    </script>
@endpush
