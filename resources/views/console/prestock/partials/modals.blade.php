{{-- Product name comes from product settings; material is chosen per prestock line (Carpet/Rubber). --}}
<div class="modal fade" id="createPrestockModal" tabindex="-1" aria-labelledby="createPrestockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPrestockModalLabel">Add prestock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createPrestockForm">
                <div class="modal-body">
                    <p class="text-muted small mb-4">Choose a product (name from product settings), material for this line, remaining stock quantity, and an optional comment.</p>

                    <div class="mb-3">
                        <label for="create-prestock-product" class="form-label">Product</label>
                        <select id="create-prestock-product" class="form-select" required>
                            <option value="">Select product</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="create-prestock-material" class="form-label">Material</label>
                        <select id="create-prestock-material" class="form-select" required>
                            <option value="Carpet">Carpet</option>
                            <option value="Rubber">Rubber</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="create-prestock-stock" class="form-label">Remaining stock</label>
                        <input type="number" class="form-control" id="create-prestock-stock" min="0" value="0" required>
                    </div>
                    <div class="mb-0">
                        <label for="create-prestock-comment" class="form-label">Comment</label>
                        <textarea class="form-control" id="create-prestock-comment" rows="3" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-nowrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editPrestockModal" tabindex="-1" aria-labelledby="editPrestockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPrestockModalLabel">Edit prestock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPrestockForm">
                <input type="hidden" id="edit-prestock-id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-prestock-product" class="form-label">Product</label>
                        <select id="edit-prestock-product" class="form-select" required>
                            <option value="">Select product</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-prestock-material" class="form-label">Material</label>
                        <select id="edit-prestock-material" class="form-select" required>
                            <option value="Carpet">Carpet</option>
                            <option value="Rubber">Rubber</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-prestock-stock" class="form-label">Remaining stock</label>
                        <input type="number" class="form-control" id="edit-prestock-stock" min="0" value="0" required>
                    </div>
                    <div class="mb-0">
                        <label for="edit-prestock-comment" class="form-label">Comment</label>
                        <textarea class="form-control" id="edit-prestock-comment" rows="3" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-nowrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
