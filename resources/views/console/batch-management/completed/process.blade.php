@extends('console.layout.app')

@section('title', 'Design Process')

@push('styles')
    <style>
        #print-labels-area {
            display: none;
        }

        /* Print styles */
        @media print {
            body * {
                visibility: hidden !important;
            }

            #print-labels-area,
            #print-labels-area * {
                visibility: visible !important;
            }

            #print-labels-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                padding: 0;
                width: 100%;
            }

            .label-row {
                display: block;
                margin: 0;
                page-break-after: always;
            }

            .label-row:last-child {
                page-break-after: auto;
            }

            .label-box {
                width: 100%;
                border: none;
                padding: 0;
                box-sizing: border-box;
            }

            @page {
                margin: 0;
            }
        }

        .step-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            position: relative;
        }

        .step-container::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }

        .step {
            width: 50px;
            height: 50px;
            background-color: white;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .step.active {
            background-color: #9289f3;
            border-color: #9289f3;
            color: white;
        }

        .step-content {
            display: none;
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .step-content.active {
            display: block;
        }

        .progress-line {
            position: absolute;
            top: 25px;
            left: 0;
            height: 2px;
            background-color: #9289f3;
            z-index: 1;
            width: 0%;
            transition: width 0.3s ease;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .order-item p {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-indicator {
            font-weight: bold;
            color: #9289f3;
            font-size: 1.1rem;
        }

        /* Production label (reference layout) */
        .label-card-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .label-card {
            background: transparent;
            border: none;
            box-shadow: none;
            width: 4in;
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
            /* margin-top: 0.05in; */
        }

        .pl-tab {
            position: absolute;
            top: 8px;
            left: 0px;
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

        #print-labels-area .production-label {
            max-width: 4in;
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-m9">
                <div class="step-container">
                    <div class="progress-line"></div>
                    <div class="step-wrapper">
                        <div class="step active" data-step="1">1</div>
                        <span class="step-label">Batch Products </span>
                    </div>
                    <div class="step-wrapper">
                        <div class="step" data-step="2">2</div>
                        <span class="step-label">Details</span>
                    </div>
                    <div class="step-wrapper">
                        <div class="step" data-step="3">3</div>
                        <span class="step-label">Type-setting</span>
                    </div>
                    <div class="step-wrapper">
                        <div class="step" data-step="4">4</div>
                        <span class="step-label">Progress</span>
                    </div>
                </div>

                <div class="card my-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title">Batch Id: <span id="batch-id">{{ $batch->batch_id }}</span></h5>
                        </div>
                        <div>
                            <h5 class="card-title">Batch Name: <span id="batch-name">{{ $batch->batch_name }}</span></h5>
                        </div>
                    </div>
                </div>

                <div class="card my-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Upload &amp; Reorder</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Upload an image or text file to extract product lines and reorder the listings below based on the position numbers in the file.
                        </p>
                        <form id="uploadReorderForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label for="uploadReorderFile" class="form-label">Upload file</label>
                                    <input
                                        type="file"
                                        class="form-control"
                                        id="uploadReorderFile"
                                        name="upload_file"
                                        accept=".jpg,.jpeg,.png,.webp,.txt,image/jpeg,image/png,image/webp,text/plain"
                                        required
                                    >
                                    <div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP, TXT (max 10MB)</div>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100" id="uploadReorderBtn">
                                        <i class="fas fa-upload me-2"></i>Upload &amp; Reorder
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="uploadReorderStatus" class="alert d-none mt-3 mb-0" role="alert"></div>
                    </div>
                </div>

                <!-- Navigation and Print Button at Top -->
                <div class="card my-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">
                                <span class="nav-indicator">1 of 3</span>
                            </h5>
                        </div>
                        <div>
                            <button class="btn btn-primary labels-btn" data-bs-toggle="modal" data-bs-target="#printLabelsModal">
                                <i class="fas fa-print me-2"></i>Print Labels
                            </button>
                        </div>
                    </div>
                </div>

                <div class="step-content active" data-step="1">
                    <div>
                        <div class="card-datatable table-responsive">
                            <table class="users-datatable table border-top">
                                <thead>
                                <tr>
                                    <th>Sr#</th>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Material Type</th>
                                    <th>Quantity</th>
                                    <th>Edging</th>
                                    <th>No. of Clips</th>
                                    <th>Order Date</th>
                                </tr>
                                </thead>
                                <tbody id="batchProductsTableBody">
                                @foreach ($batch->orders as $order)

                                    @php
                                        $productSetting = $order->product_setting;
                                    @endphp
                                    <tr data-listing-id="{{ $order->id }}">
                                        <td class="row-index">{{ $loop->iteration }}</td>
                                        <td>{{ $order->order_id }}</td>
                                        <td>{{ $order->make_model ?? '-' }}</td>
                                        <td>{{ $productSetting->material_type ?? $order->material_type ?? '-' }}</td>
                                        <td>{{ $order->quantity }}</td>
                                        <td>{{ $order->edging ?? '-' }}</td>
                                        <td>{{ $productSetting->no_of_clips ?? '-' }}</td>
                                        <td>{{ $order->order_date }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="btn-container">
                        <div></div>
                        <button class="labels-btn" data-bs-toggle="modal" data-bs-target="#printLabelsModal">Print Labels</button>
                        <button class="next-btn">Next</button>
                    </div>
                </div>

                <div class="step-content" data-step="2">
                    <div class="row row-cols-1 row-cols-md-3 g-4" id="batchDetailsCards">
                        @foreach ($batch->orders as $order)
                            @php
                                $productSetting = $order->product_setting;
                            @endphp
                            <div class="col d-flex align-items-stretch" data-listing-id="{{ $order->id }}">
                                <div class="card">
                                    <img src="{{ $productSetting ? ($productSetting->imageFile ? asset('storage/' . $productSetting->imageFile->path) : ($productSetting->dxfFile ? asset('storage/' . $productSetting->dxfFile->path) : asset('themes/console/assets/img/pages/mat.jpg'))) : asset('themes/console/assets/img/pages/mat.jpg') }}"
                                         class="img-fluid w-100" style="height: 370px" alt="design-file" />

                                    <div class="card-body">
                                        <div class="order-detail-content">
                                            <div class="order-item">
                                                <p>Order Id:<span>{{ $order->order_id }}</span></p>
                                            </div>
                                            <div class="order-item">
                                                <p>Design Type:<span>{{ $order->sku }}</span></p>
                                            </div>
                                            <div class="order-item">
                                                <p>Product:<span style="margin-left: 20px">{{ $order->make_model }}</span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="btn-container">
                        <button class="prev-btn">Previous</button>
                        <button class="labels-btn" data-bs-toggle="modal" data-bs-target="#printLabelsModal">Print Labels</button>
                        <button class="next-btn">Next</button>
                    </div>
                </div>

                <div class="step-content" data-step="3">
                    <div>
                        <img src="{{ asset('themes/console/assets/img/pages/matsGroup.png') }}"
                             class="img-fluid w-100 h-auto" alt="visa-card" />
                    </div>
                    <div class="btn-container">
                        <button class="prev-btn">Previous</button>
                        <button class="labels-btn" data-bs-toggle="modal" data-bs-target="#printLabelsModal">Print Labels</button>
                        <button class="next-btn">Next</button>
                    </div>
                </div>
                <div class="step-content" data-step="4">
                    <div>
                        <img src="{{ asset('themes/console/assets/img/pages/blankscreen.png') }}"
                             class="img-fluid w-100 h-auto" alt="visa-card" />
                    </div>
                    <div class="btn-container">
                        <button class="prev-btn">Previous</button>
                        <button class="labels-btn" data-bs-toggle="modal" data-bs-target="#printLabelsModal">Print Labels</button>
                        <button class="finish-btn">Finish</button>
                    </div>
                </div>
                <div id="print-labels-area">
                    @foreach ($batch->orders as $order)
                        <div class="label-row">
                            <div class="label-box" data-listing-id="{{ $order->id }}">
                                @include('console.batch-management.completed.partials.production-label', [
                                    'order' => $order,
                                    'productSetting' => $order->product_setting,
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <form class="d-none" action="{{ route('console.batchManagement.completed.store') }}" method="POST">
                @csrf
                <input type="hidden" name="batch_id" value="{{ $batch->id }}">
            </form>
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
                        <!-- Print Label Button at Top Right -->
                        <div class="position-absolute" style="top: 20px; right: 20px; z-index: 9999;">
                            <button class="btn btn-primary btn-sm print-single-label" data-order-id="">
                                <i class="ti ti-printer me-1"></i>
                                Print
                            </button>
                        </div>

                        <!-- Left Navigation Button -->
                        <button class="btn btn-outline-primary btn-lg prev-label-btn position-absolute" style="left: 20px; top: 50%; transform: translateY(-50%); z-index: 9999; background-color: white; border: 2px solid #0d6efd; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                            &larr;
                        </button>

                        <!-- Right Navigation Button -->
                        <button class="btn btn-outline-primary btn-lg next-label-btn position-absolute" style="right: 20px; top: 50%; transform: translateY(-50%); z-index: 9999; background-color: white; border: 2px solid #0d6efd; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                            &rarr;
                        </button>

                        <div id="labelCardsWrapper" class="w-100">
                        @foreach ($batch->orders as $index => $order)
                            <div class="label-card-container {{ $index === 0 ? 'active' : 'd-none' }}" data-index="{{ $index }}" data-order-id="{{ $order->order_id }}" data-listing-id="{{ $order->id }}">
                                <div class="card label-card">
                                    <div class="card-body">
                                        @include('console.batch-management.completed.partials.production-label', [
                                            'order' => $order,
                                            'productSetting' => $order->product_setting,
                                        ])
                                        <div class="label-navigation">
                                            <span class="fs-5">Label {{ $index + 1 }} of {{ count($batch->orders) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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

        function refreshLabelNavigationAfterReorder() {
            currentLabelIndex = 0;
            showLabel(0);
        }

        $(document).ready(function() {
            var currentStep = 1;
            var totalSteps = 4;

            // Update progress line
            function updateProgressLine() {
                var progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
                $('.progress-line').css('width', progressPercentage + '%');
            }

            // Update navigation indicator
            function updateNavigationIndicator() {
                $('.nav-indicator').text(currentStep + ' of ' + totalSteps);
            }

            // Initialize navigation indicator
            updateNavigationIndicator();

            // Next button click event
            $('.next-btn').click(function() {
                if (currentStep < totalSteps) {
                    // Hide current step content
                    $('.step-content[data-step="' + currentStep + '"]').removeClass('active');

                    // Increment current step
                    currentStep++;

                    // Show next step content
                    $('.step-content[data-step="' + currentStep + '"]').addClass('active');

                    // Update step indicator
                    $('.step[data-step="' + currentStep + '"]').addClass('active');

                    // Update progress line
                    updateProgressLine();

                    // Update navigation indicator
                    updateNavigationIndicator();
                }
            });

            // Previous button click event
            $('.prev-btn').click(function() {
                if (currentStep > 1) {
                    // Hide current step content
                    $('.step-content[data-step="' + currentStep + '"]').removeClass('active');

                    // Remove active class from current step
                    $('.step[data-step="' + currentStep + '"]').removeClass('active');

                    // Decrement current step
                    currentStep--;

                    // Show previous step content
                    $('.step-content[data-step="' + currentStep + '"]').addClass('active');

                    // Update progress line
                    updateProgressLine();

                    // Update navigation indicator
                    updateNavigationIndicator();
                }
            });

            // Finish button click event
            $('.finish-btn').click(function() {
                $('form[action="{{ route('console.batchManagement.completed.store') }}"]').submit();
            });

            // Print single label
            $('.print-single-label').click(function() {
                var orderId = $(this).data('order-id');
                printSingleLabel(orderId);
            });

            // Next label button
            $(document).on('click', '.next-label-btn', function() {
                if (currentLabelIndex < getTotalLabels() - 1) {
                    showLabel(currentLabelIndex + 1);
                }
            });

            // Previous label button
            $(document).on('click', '.prev-label-btn', function() {
                if (currentLabelIndex > 0) {
                    showLabel(currentLabelIndex - 1);
                }
            });

            // Reset to first label when modal opens
            $('#printLabelsModal').on('show.bs.modal', function() {
                refreshLabelNavigationAfterReorder();
            });

            $('#uploadReorderForm').on('submit', function(event) {
                event.preventDefault();

                const fileInput = document.getElementById('uploadReorderFile');
                const submitBtn = document.getElementById('uploadReorderBtn');

                if (!fileInput.files.length) {
                    showUploadStatus('error', 'Please choose a file to upload.');
                    return;
                }

                const formData = new FormData(this);
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                showUploadStatus('info', 'Processing upload. Image files may take a little longer while OCR runs.');

                fetch("{{ route('console.batchManagement.completed.uploadAndReorder') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                })
                    .then(async (response) => {
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Failed to process the uploaded file.');
                        }

                        return data;
                    })
                    .then((data) => {
                        const orderedIds = data.ordered_orders.map((order) => String(order.id));
                        reorderListingSections(orderedIds);

                        const warning = buildReorderWarningMessage(data.summary);
                        if (warning) {
                            showUploadStatus('warning', warning);
                        } else {
                            showUploadStatus('success', data.message || 'Listings reordered successfully.');
                        }
                    })
                    .catch((error) => {
                        showUploadStatus('error', error.message || 'Failed to process the uploaded file.');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload & Reorder';
                    });
            });
        });

        function showUploadStatus(type, message) {
            const statusEl = document.getElementById('uploadReorderStatus');
            const classMap = {
                success: 'alert-success',
                error: 'alert-danger',
                info: 'alert-info',
                warning: 'alert-warning',
            };

            statusEl.className = 'alert mt-3 mb-0 ' + (classMap[type] || 'alert-info');
            statusEl.textContent = message;
            statusEl.classList.remove('d-none');
        }

        function buildReorderWarningMessage(summary) {
            if (!summary) {
                return null;
            }

            const problems = [];

            const notInUpload = summary.listings_not_in_upload || [];
            if (notInUpload.length) {
                const names = notInUpload.map((item) => item.listing_name || ('#' + item.order_id)).join(', ');
                problems.push(notInUpload.length + ' listing(s) were not found in the uploaded image and were kept at the end: ' + names);
            }

            if (summary.total_unmatched_ocr_lines > 0) {
                problems.push(summary.total_unmatched_ocr_lines + ' line(s) in the image could not be matched to any listing in this batch.');
            }

            const duplicates = summary.duplicate_matched_products || [];
            if (duplicates.length) {
                const names = duplicates.map((item) => item.listing_name || ('#' + item.order_id)).join(', ');
                problems.push('Duplicate labels detected for: ' + names);
            }

            if (!problems.length) {
                return null;
            }

            return 'Reordered with warnings — please verify the order. ' + problems.join(' ');
        }

        function reorderListingSections(orderedIds) {
            reorderChildrenByListingIds(document.getElementById('batchProductsTableBody'), orderedIds, 'tr[data-listing-id]');
            updateTableRowIndexes();

            reorderChildrenByListingIds(document.getElementById('batchDetailsCards'), orderedIds, '[data-listing-id]');
            reorderLabelModalCards(orderedIds);
            rebuildPrintLabelArea(orderedIds);
        }

        function reorderChildrenByListingIds(container, orderedIds, selector) {
            if (!container) {
                return;
            }

            const items = Array.from(container.querySelectorAll(selector));
            const itemMap = new Map(items.map((item) => [String(item.dataset.listingId), item]));

            orderedIds.forEach((listingId) => {
                const item = itemMap.get(String(listingId));
                if (item) {
                    container.appendChild(item);
                }
            });

            items.forEach((item) => {
                if (!orderedIds.includes(String(item.dataset.listingId))) {
                    container.appendChild(item);
                }
            });
        }

        function updateTableRowIndexes() {
            document.querySelectorAll('#batchProductsTableBody tr').forEach((row, index) => {
                const indexCell = row.querySelector('.row-index');
                if (indexCell) {
                    indexCell.textContent = index + 1;
                }
            });
        }

        function reorderLabelModalCards(orderedIds) {
            const wrapper = document.getElementById('labelCardsWrapper');
            if (!wrapper) {
                return;
            }

            reorderChildrenByListingIds(wrapper, orderedIds, '.label-card-container');
            refreshLabelNavigationAfterReorder();
        }

        function rebuildPrintLabelArea(orderedIds) {
            const printArea = document.getElementById('print-labels-area');
            if (!printArea) {
                return;
            }

            const labelBoxes = Array.from(printArea.querySelectorAll('.label-box'));
            const labelMap = new Map(labelBoxes.map((box) => [String(box.dataset.listingId), box]));
            const orderedBoxes = orderedIds
                .map((listingId) => labelMap.get(String(listingId)))
                .filter(Boolean);

            printArea.innerHTML = '';

            orderedBoxes.forEach((box) => {
                const row = document.createElement('div');
                row.className = 'label-row';
                row.appendChild(box);
                printArea.appendChild(row);
            });
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
                    left: 0px;
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

        // Function to print a single label
        function printSingleLabel(orderId) {
            var labelShell = document.querySelector('.label-card-container.active .production-label');
            var labelHtml = labelShell ? labelShell.outerHTML : '';
            openLabelPrintWindow('Print Label - ' + orderId, labelHtml);
        }

        // Function to print all labels
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
    </script>
@endpush
