<div class="modal fade" id="addOrEditCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="collectionModalTitle">Add Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="row">
                        <div class="col-md-6 mb-3 collections-product-field">
                            <label for="product_setting_id" class="form-label">Product</label>
                            <select class="form-select collections-product-select" id="product_setting_id" name="product_setting_id" required>
                                <option value="">Select product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" title="{{ $product->name }}" data-full-name="{{ $product->name }}">
                                        {{ \Illuminate\Support\Str::limit($product->name, 50, '***') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="material" class="form-label">Material</label>
                            <select class="form-select" id="material" name="material" required>
                                <option value="Carpet">Carpet</option>
                                <option value="Rubber">Rubber</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edging" class="form-label">Edging Color</label>
                            <select class="form-select" id="edging" name="edging">
                                <option value="">Select edging color</option>
                                <option value="Blue">Blue</option>
                                <option value="Grey">Grey</option>
                                <option value="Black">Black</option>
                                <option value="Green">Green</option>
                                <option value="White">White</option>
                                <option value="Red">Red</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
