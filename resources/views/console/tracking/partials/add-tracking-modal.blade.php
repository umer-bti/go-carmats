<div class="modal fade" id="addTrackingModal" tabindex="-1" aria-labelledby="addTrackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTrackingModalLabel">
                    <i class="fas fa-shipping-fast me-2"></i>Add Tracking Number
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTrackingForm">
                    <div class="mb-3">
                        <label for="order_select" class="form-label fw-semibold">Select Order (Awaiting Shipment):</label>
                        <select id="order_select" class="form-select" required>
                            <option value="">-- Select an order --</option>
                        </select>
                        <small class="text-muted">Only orders with "awaiting_shipment" status are shown.</small>
                    </div>

                    <!-- Order Details Preview (shown after selection) -->
                    <div id="orderPreview" class="card mb-3" style="display: none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Selected Order Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Order ID:</strong> <span id="preview-order-id">-</span></p>
                                    <p class="mb-1"><strong>SKU:</strong> <span id="preview-sku">-</span></p>
                                    <p class="mb-1"><strong>Product:</strong> <span id="preview-make-model">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Material:</strong> <span id="preview-material">-</span></p>
                                    <p class="mb-1"><strong>Recipient:</strong> <span id="preview-recipient">-</span></p>
                                    <p class="mb-1"><strong>City:</strong> <span id="preview-city">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tracking_number_input" class="form-label fw-semibold">Tracking Number:</label>
                        <input type="text" class="form-control" id="tracking_number_input" placeholder="Enter tracking number" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddTracking()">
                    <i class="fas fa-save me-1"></i> Add Tracking
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('addTrackingModal');
        
        // Initialize Select2 and load awaiting_shipment orders when modal is shown
        modal.addEventListener('shown.bs.modal', function () {
            const $orderSelect = $('#order_select');
            
            if (!$orderSelect.data('select2')) {
                $orderSelect.select2({
                    width: '100%',
                    placeholder: 'Search and select an order...',
                    dropdownParent: $('#addTrackingModal'),
                    ajax: {
                        url: '{{ route('console.tracking.getAwaitingShipmentOrders') }}',
                        dataType: 'json',
                        delay: 250,
                        processResults: function (data) {
                            return {
                                results: data.data.map(order => ({
                                    id: order.id,
                                    text: `${order.order_id} - ${order.make_model} (${order.recipient_name})`,
                                    order: order
                                }))
                            };
                        }
                    }
                });
                
                // Show order preview when selected
                $orderSelect.on('select2:select', function (e) {
                    const order = e.params.data.order;
                    document.getElementById('preview-order-id').textContent = order.order_id || '-';
                    document.getElementById('preview-sku').textContent = order.sku || '-';
                    document.getElementById('preview-make-model').textContent = order.make_model || '-';
                    document.getElementById('preview-material').textContent = order.material_type || '-';
                    document.getElementById('preview-recipient').textContent = order.recipient_name || '-';
                    document.getElementById('preview-city').textContent = order.city || '-';
                    document.getElementById('orderPreview').style.display = 'block';
                });
            }
        });
        
        // Reset modal on hide
        modal.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('addTrackingForm');
            form.reset();
            $('#order_select').val(null).trigger('change');
            document.getElementById('orderPreview').style.display = 'none';
        });
    });

    function submitAddTracking() {
        const orderId = document.getElementById('order_select').value;
        const trackingNumber = document.getElementById('tracking_number_input').value;
        const submitBtn = event.target;

        if (!orderId) {
            toastr.error('Please select an order.');
            return;
        }

        if (!trackingNumber) {
            toastr.error('Please enter a tracking number.');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';

        fetch('{{ route('console.tracking.addTracking') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                tracking_number: trackingNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success!', data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addTrackingModal')).hide();
                $('#trackingTable').DataTable().ajax.reload();
            } else {
                toastr.error(data.message || 'Failed to add tracking.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Something went wrong. Try again later.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        });
    }
</script>

