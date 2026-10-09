<!-- Modal for adding design reference -->
<style>
    /* Styling for the searchable input with datalist */
    #name {
        cursor: text;
    }
    
    /* Add a subtle dropdown indicator */
    #name::-webkit-calendar-picker-indicator {
        opacity: 0.5;
        cursor: pointer;
    }
    
    #name::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }
</style>

<div class="modal fade" id="addOrEditProductFileModal" tabindex="-1" aria-labelledby="addDesignReferenceModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDesignReferenceModalLabel">Add New Product File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form enctype="multipart/form-data">

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="name" placeholder="Search existing or type a new product name" required list="productNames">
                            <datalist id="productNames">
                                @foreach ($products as $product)
                                    <option value="{{ $product }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label">Code</label>
                        <input type="text" class="form-control" id="code" placeholder="Enter product code">
                    </div>
                    <div class="mb-3">
                        <label for="skus" class="form-label">SKUs</label>
                        <select id="skus" class="form-select" multiple="multiple"></select>
                        <small class="text-muted">Type and press Enter to add new SKUs. Multiple allowed.</small>
                    </div>
                    <div class="mb-3">
                        <label for="noOfClips" class="form-label">No. of Clips</label>
                        <input type="text" class="form-control" id="noOfClips" placeholder="Enter number of clips">
                    </div>
                    <div class="mb-3">
                        <label for="noOfMats" class="form-label">No. of mats</label>
                        <input type="number" class="form-control" id="noOfMats" placeholder="Enter number of mats">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" placeholder="Enter Description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label">Design File</label>
                        <input type="file" class="form-control dropify" id="file" accept=".dxf" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary">
                            <span class="btn-text">Upload</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Uploading...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple enhancement for the datalist input
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('name');
        
        if (nameInput) {
            // Show datalist on focus for better UX
            nameInput.addEventListener('focus', function() {
                this.click(); // This triggers the datalist dropdown
            });
            
            // Fetch and populate SKUs when product name is selected/changed
            let fetchTimeout;
            nameInput.addEventListener('input', function() {
                clearTimeout(fetchTimeout);
                const productName = this.value.trim();
                
                // Only fetch if name is not empty and is in create mode
                const modalElement = document.getElementById('addOrEditProductFileModal');
                const form = modalElement.querySelector('form');
                const isEdit = form.hasAttribute('data-id');
                
                if (productName ) {
                    // Debounce the API call
                    fetchTimeout = setTimeout(() => {
                        fetchSkusForProduct(productName);
                    }, 500);
                }
            });
            
            // Also trigger on datalist selection
            nameInput.addEventListener('change', function() {
                const productName = this.value.trim();
                const modalElement = document.getElementById('addOrEditProductFileModal');
                const form = modalElement.querySelector('form');
                const isEdit = form.hasAttribute('data-id');
                
                if (productName && !isEdit) {
                    fetchSkusForProduct(productName);
                }
            });
        }
        
        function fetchSkusForProduct(productName) {
            fetch('{{ route('console.products.getSkusByName') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_name: productName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.skus && data.skus.length > 0) {
                    const $skus = $('#skus');
                    if ($skus.data('select2')) {
                        // Clear existing selections
                        $skus.val([]).trigger('change');
                        
                        // Remove all existing options
                        $skus.empty();
                        
                        // Add new SKUs as options and select them
                        data.skus.forEach(sku => {
                            const newOption = new Option(sku, sku, true, true);
                            $skus.append(newOption);
                        });
                        
                        $skus.trigger('change');
                        toastr.success(`Auto-populated ${data.skus.length} SKU(s) from orders.`);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching SKUs:', error);
            });
        }

        // Form submission handling to prevent duplicate submissions
        const modalElement = document.getElementById('addOrEditProductFileModal');
        const form = modalElement.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        let isSubmitting = false;

        // Initialize Select2 and prefill (if provided) when modal is shown
        modalElement.addEventListener('shown.bs.modal', function () {
            const $skus = $('#skus');
            if (!$skus.data('select2')) {
                $skus.select2({
                    tags: true,
                    tokenSeparators: [',', ' '],
                    width: '100%',
                    placeholder: 'Add one or more SKUs',
                    allowClear: true,
                    dropdownParent: $('#addOrEditProductFileModal')
                });
            }

            // Prefill SKUs if dataset provided
            const prefill = modalElement.dataset.prefillSkus;
            if (prefill) {
                let skus = [];
                try { skus = JSON.parse(prefill) || []; } catch (_) { skus = []; }
                $skus.val([]).trigger('change');
                const currentOptions = new Set(($skus.find('option') || []).toArray().map(o => o.value));
                skus.forEach(sku => {
                    if (!currentOptions.has(sku)) {
                        const newOption = new Option(sku, sku, false, false);
                        $skus.append(newOption);
                    }
                });
                $skus.val(skus).trigger('change');
                delete modalElement.dataset.prefillSkus;
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isSubmitting) {
                return; // Prevent duplicate submissions
            }

            isSubmitting = true;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').classList.add('d-none');
            submitBtn.querySelector('.btn-loading').classList.remove('d-none');

            const formData = new FormData(form);
            formData.append('name', modalElement.querySelector('#name').value);
            formData.append('no_of_clips', modalElement.querySelector('#noOfClips').value);
            formData.append('no_of_mats', modalElement.querySelector('#noOfMats').value);
            formData.append('description', modalElement.querySelector('#description').value.trim());
            const fileInputEl = modalElement.querySelector('#file');
            if (fileInputEl && fileInputEl.files && fileInputEl.files[0]) {
                formData.append('file', fileInputEl.files[0]);
            }
            formData.append('code', modalElement.querySelector('#code').value);
            const selectedSkus = $('#skus').val() || [];
            selectedSkus.forEach(sku => formData.append('skus[]', sku));

            const isEdit = form.hasAttribute('data-id');
            let url = '{{ route('console.products.store') }}';
            let method = 'POST';

            if (isEdit) {
                url = `{{ route('console.products.update') }}`;
                formData.append('_method', 'PUT');
                formData.append('id', form.getAttribute('data-id'));
            }

            fetch(url, {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    bootstrap.Modal.getInstance(modalElement).hide();
                    form.reset();
                    form.removeAttribute('data-id');
                    $('#product-table').DataTable().ajax.reload();
                } else {
                    toastr.error(data.message || 'Something went wrong.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Something went wrong. Try again later.');
            })
            .finally(() => {
                // Reset submission state
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.querySelector('.btn-text').classList.remove('d-none');
                submitBtn.querySelector('.btn-loading').classList.add('d-none');
            });
        });
    }); // End DOMContentLoaded
</script>
