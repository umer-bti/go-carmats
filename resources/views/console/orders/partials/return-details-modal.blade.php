<div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-labelledby="returnDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="returnDetailsModalLabel">
                    <i class="fas fa-undo me-2"></i>Return Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Order Information -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Order Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold">Order ID:</td>
                                            <td id="modal-order-id">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">SKU:</td>
                                            <td id="modal-sku">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Product:</td>
                                            <td id="modal-make-model">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Material:</td>
                                            <td id="modal-material-type">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Edging:</td>
                                            <td id="modal-order-edging">—</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Return Information -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Return Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold">Item Name:</td>
                                            <td id="modal-return-item-name">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Return SKU:</td>
                                            <td id="modal-return-sku">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Material Type:</td>
                                            <td id="modal-return-material">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Edging:</td>
                                            <td id="modal-return-edging">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Return Date:</td>
                                            <td id="modal-return-date">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Return Type:</td>
                                            <td id="modal-return-type">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Status:</td>
                                            <td id="modal-return-status">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Tracking:</td>
                                            <td id="modal-tracking">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Received:</td>
                                            <td id="modal-received">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Notes:</td>
                                            <td id="modal-notes">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Return Reason -->
                    <div class="col-12 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Return Reason</h6>
                            </div>
                            <div class="card-body">
                                <p id="modal-reason" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showReturnDetails(element) {
        const orderId = element.getAttribute('data-order-id');
        const returnId = element.getAttribute('data-return-id');

        if (!orderId) {
            toastr.error('Order ID not found.');
            return;
        }

        // Fetch return details
        fetch(`{{ route('console.orders.returnDetails') }}?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const order = data.data.order;
                    const returnData = data.data.return;

                    // Populate order information
                    document.getElementById('modal-order-id').textContent = order.order_id || '-';
                    document.getElementById('modal-sku').textContent = order.sku || '-';
                    document.getElementById('modal-make-model').textContent = order.make_model || '-';
                    document.getElementById('modal-material-type').textContent = order.material_type || '-';
                    const orderEdging = order.edging != null && String(order.edging).trim() !== ''
                        ? String(order.edging).trim()
                        : '—';
                    document.getElementById('modal-order-edging').textContent = orderEdging;

                    // Populate return information
                    document.getElementById('modal-return-item-name').textContent = returnData.item_name || '-';
                    document.getElementById('modal-return-sku').textContent = returnData.return_sku || '-';
                    document.getElementById('modal-return-material').textContent = returnData.return_material_type || '-';
                    document.getElementById('modal-return-edging').textContent = returnData.return_edging || returnData.edging || '-';
                    document.getElementById('modal-return-date').textContent = returnData.return_request_date || '-';
                    document.getElementById('modal-return-type').textContent = returnData.return_type || '-';
                    document.getElementById('modal-return-status').textContent = returnData.status || '-';
                    document.getElementById('modal-tracking').textContent = returnData.tracking || '-';
                    
                    // Received status badge
                    const receivedEl = document.getElementById('modal-received');
                    if (returnData.received) {
                        receivedEl.innerHTML = '<span class="badge bg-success">Yes</span>';
                    } else {
                        receivedEl.innerHTML = '<span class="badge bg-warning">Pending</span>';
                    }

                    // Reason
                    document.getElementById('modal-reason').textContent = returnData.reason || '-';

                    // Notes
                    document.getElementById('modal-notes').textContent = returnData.notes || '-';

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('returnDetailsModal'));
                    modal.show();
                } else {
                    toastr.error(data.message || 'Failed to load return details.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Failed to load return details.');
            });
    }
</script>
