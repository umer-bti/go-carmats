<div class="modal fade" id="printLabelsModal" tabindex="-1" aria-labelledby="printLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="printLabelsModalLabel">
                    Print Label
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-0" style="height: calc(100vh - 140px); position: relative;">

                <!-- 🔥 PRINT BUTTON (STATIC - NEVER OVERWRITTEN) -->
                <div class="position-absolute" style="top: 20px; right: 20px; z-index: 9999;">
                    <button class="btn btn-primary btn-sm print-single-label">
                        <i class="ti ti-printer me-1"></i> Print
                    </button>
                </div>

                <!-- 🔄 LABEL AREA ONLY -->
                <div id="labelContainer"
                    class="w-100 h-100 d-flex flex-column justify-content-center align-items-center">

                    <!-- Loader -->
                    <div id="labelLoader" class="text-center">
                        <p class="mb-0">Loading label...</p>
                    </div>

                </div>

            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
