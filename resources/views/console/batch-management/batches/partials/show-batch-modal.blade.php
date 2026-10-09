<div class="modal fade" id="showBatchModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Create Batch</h1>
                <button type="button" id="showBatchModalCloseButton" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="batchid" class="form-label">Batch Id</label>
                            <input type="text" class="form-control" id="batchid" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="batchname" class="form-label">Batch Name</label>
                            <input type="text" class="form-control" id="batchname" readonly>
                        </div>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-3 g-4" id="ordersContainer">
                </div>
            </div>
        </div>
    </div>
</div>
