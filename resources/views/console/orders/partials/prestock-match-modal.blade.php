<div class="modal fade" id="prestockMatchModal" tabindex="-1" aria-labelledby="prestockMatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="prestockMatchModalLabel">
                    <i class="fas fa-box me-2"></i>Prestock match
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="prestock-match-basis">-</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Order</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold">Order ID:</td>
                                            <td id="prestock-modal-order-id">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">SKU:</td>
                                            <td id="prestock-modal-sku">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Product:</td>
                                            <td id="prestock-modal-make-model">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Material:</td>
                                            <td id="prestock-modal-order-material">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Edging:</td>
                                            <td id="prestock-modal-order-edging">—</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-warehouse me-2"></i>Prestock line</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold">Product name:</td>
                                            <td id="prestock-modal-ps-name">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Material:</td>
                                            <td id="prestock-modal-ps-material">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Edging:</td>
                                            <td id="prestock-modal-ps-edging">—</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Remaining stock:</td>
                                            <td id="prestock-modal-ps-stock">-</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Comment:</td>
                                            <td id="prestock-modal-ps-comment">-</td>
                                        </tr>
                                    </tbody>
                                </table>
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
    function showPrestockMatchDetails(element) {
        const orderId = element.getAttribute('data-order-id');

        if (!orderId) {
            toastr.error('Order ID not found.');
            return;
        }

        fetch(`{{ route('console.orders.prestockMatchDetails') }}?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const order = data.data.order;
                    const ps = data.data.prestock;

                    document.getElementById('prestock-match-basis').textContent = data.data.match_basis || '';
                    document.getElementById('prestock-modal-order-id').textContent = order.order_id || '-';
                    document.getElementById('prestock-modal-sku').textContent = order.sku || '-';
                    document.getElementById('prestock-modal-make-model').textContent = order.make_model || '-';
                    document.getElementById('prestock-modal-order-material').textContent = order.material_type || '-';
                    const orderEdging = order.edging != null && String(order.edging).trim() !== '' ? String(order.edging).trim() : '—';
                    document.getElementById('prestock-modal-order-edging').textContent = orderEdging;

                    document.getElementById('prestock-modal-ps-name').textContent = ps.product_name || '-';
                    document.getElementById('prestock-modal-ps-material').textContent = ps.material || '-';
                    const psEdging = ps.edging != null && String(ps.edging).trim() !== '' ? String(ps.edging).trim() : '—';
                    document.getElementById('prestock-modal-ps-edging').textContent = psEdging;

                    const rem = ps.remaining_stock ?? ps.stock_after_reserve;
                    document.getElementById('prestock-modal-ps-stock').textContent =
                        rem !== undefined && rem !== null ? String(rem) : '-';
                    document.getElementById('prestock-modal-ps-comment').textContent = ps.comment && String(ps.comment).trim() !== '' ? ps.comment : '—';

                    const modal = new bootstrap.Modal(document.getElementById('prestockMatchModal'));
                    modal.show();
                } else {
                    toastr.error(data.message || 'Failed to load prestock details.');
                }
            })
            .catch(() => {
                toastr.error('Failed to load prestock details.');
            });
    }
</script>
