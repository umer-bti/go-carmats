<div class="modal fade" id="markAsReceivedModal" tabindex="-1" aria-labelledby="markAsReceivedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markAsReceivedModalLabel">Mark Return as Received and Print Label</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="markAsReceivedForm">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" rows="4" placeholder="Add any notes about this return..."></textarea>
                        <small class="text-muted">You can add notes about the condition, reason, or any other relevant information.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitMarkAsReceived()">
                    <i class="fas fa-check me-1"></i> Mark as Received and Print Label
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function submitMarkAsReceived() {
        const modal = document.getElementById('markAsReceivedModal');
        const form = modal.querySelector('form');
        const returnId = form.getAttribute('data-return-id');
        const notes = form.querySelector('#notes').value;

        if (!returnId) {
            toastr.error('Return ID not found.');
            return;
        }

        fetch('{{ route('console.returns.markAsReceived') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: returnId,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success!', data.message, 'success');
                bootstrap.Modal.getInstance(modal).hide();
                $('#returnsTable').DataTable().ajax.reload();
                
                // Generate and print label
                if (data.return_data && data.return_data.tracking) {
                    printReturnLabel(data.return_data);
                }
            } else {
                toastr.error(data.message || 'Something went wrong.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Something went wrong. Try again later.');
        });
    }

    function printReturnLabel(returnData) {
        // Fetch barcode for tracking number
        fetch('{{ route('console.returns.generateLabelBarcode') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tracking: returnData.tracking
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create print window with label
                const printWindow = window.open('', '_blank');
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Return Label - ${returnData.tracking}</title>
                        <style>
                            @media print {
                                @page { margin: 10mm; }
                            }
                            body { 
                                margin: 0; 
                                padding: 20px; 
                                font-family: Arial, sans-serif; 
                            }
                            .label-box {
                            
                                padding: 15px;
                                max-width: 600px;
                                margin: 0 auto;
                            }
                            .barcode-section {
                                text-align: center;
                                margin-bottom: 20px;
                                padding: 10px;
                                border-bottom: 1px solid #ccc;
                            }
                            .barcode-section img {
                                max-width: 100%;
                                height: auto;
                            }
                            .barcode-section p {
                                margin-top: 5px;
                                font-size: 14px;
                                font-weight: bold;
                            }
                            .info-section {
                                margin: 15px 0;
                            }
                            .info-section p {
                                margin: 8px 0;
                                font-size: 14px;
                            }
                            .info-section strong {
                                display: inline-block;
                                width: 120px;
                            }
                            .notes-section {
                                margin-top: 20px;
                                padding: 10px;
                                background-color: #f8f9fa;
                                border: 1px solid #dee2e6;
                                border-radius: 4px;
                            }
                            .notes-section p {
                                margin: 5px 0;
                                font-size: 13px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="label-box">
                            <div class="barcode-section">
                                <img src="data:image/png;base64,${data.barcode}" alt="Barcode">
                                <p>${returnData.tracking}</p>
                            </div>
                            <div class="info-section">
                                <p><strong>Product Name:</strong> ${returnData.item_name || '-'}</p>
                            </div>
                            ${returnData.notes ? `
                                <div class="notes-section">
                                    <p><strong>Notes:</strong></p>
                                    <p>${returnData.notes}</p>
                                </div>
                            ` : ''}
                        </div>
                    </body>
                    </html>
                `;

                printWindow.document.write(printContent);
                printWindow.document.close();

                // Wait for images to load before printing
                printWindow.onload = function() {
                    printWindow.focus();
                    printWindow.print();
                    
                    // Close the print window after printing or when dialog is closed
                    setTimeout(function() {
                       printWindow.close();
                    }, 100);
                };
            } else {
                toastr.error('Failed to generate barcode for label.');
            }
        })
        .catch(error => {
            console.error('Error generating barcode:', error);
            toastr.error('Failed to generate label.');
        });
    }
</script>

