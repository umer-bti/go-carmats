<div class="card-datatable text-nowrap">
    <div class="table-responsive p-5 m-0 pt-0">
        <table id="{{ $id }}" class="datatables-basic table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{!! $column['label'] !!}</th>
                    @endforeach
                </tr>
            </thead>
        </table>
    </div>
</div>

@push('styles')
    <style>
        /* Scope search box styling to this table instance */
        #{{ $id }}_wrapper .dt-search,
        #{{ $id }}_wrapper .dataTables_filter {
            text-align: right;
        }

        #{{ $id }}_wrapper .dt-search input,
        #{{ $id }}_wrapper .dataTables_filter input {
            max-width: 220px;
            width: auto !important;
            display: inline-block;
        }

        /* Limit width of entries dropdown */
        #{{ $id }}_wrapper .dataTables_length select,
        #{{ $id }}_wrapper .dt-length select {
            width: auto !important;
            min-width: 70px;
            max-width: 100px;
            display: inline-block;
            margin-left: 10px;
        }

        #{{ $id }}_wrapper .dataTables_length,
        #{{ $id }}_wrapper .dt-length {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
@endpush

@if($attributes->get('export'))
    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.0/css/buttons.bootstrap5.min.css">
    @endpush
@endif

@push('scripts')
    <script src="{{ asset('themes/console/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>

    @if($attributes->get('export'))
        <script src="https://cdn.datatables.net/buttons/3.0.0/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/3.0.0/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/3.0.0/js/buttons.html5.min.js"></script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const tableId = '{{ $id }}';
            const checkboxCallback = @json($attributes->get('checkbox-callback'));
            const enableExport = @json((bool) $attributes->get('export'));
            const exportFileName = @json($attributes->get('export-filename', $id));
            const tableElement = document.getElementById(tableId);
            const wrapper = tableElement.closest('.card-datatable');

            // Process columns to ensure orderable is properly set
            const columnsConfig = {!! json_encode($columns) !!};
            let defaultOrder = [];
            
            columnsConfig.forEach((col, index) => {
                // If orderable is not explicitly set, default to true
                if (col.orderable === undefined) {
                    col.orderable = true;
                }
                
                // Check if this column has default ordering
                if (col.default_order) {
                    const direction = col.default_order === 'desc' ? 'desc' : 'asc';
                    defaultOrder.push([index, direction]);
                    // Remove default_order from column config as DataTables doesn't recognize it
                    delete col.default_order;
                }

                // If column is flagged as raw HTML, attach a passthrough render function
                if (col.render === true) {
                    col.render = function(data) { return data; };
                } else if (typeof col.render === 'string' && typeof window[col.render] === 'function') {
                    col.render = window[col.render];
                }
            });

            // ✅ Initialize DataTable
            const dtOptions = {
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{!! $ajax !!}',
                    data: function(d) {
                        @if (!empty($filters) && is_array($filters))
                            @foreach ($filters as $filter)
                                d['{{ $filter }}'] = document.getElementById(
                                    '{{ $filter }}')?.value;
                            @endforeach
                        @endif
                    }
                },
                columns: columnsConfig,
                // lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                lengthMenu: [[10, 30, 50, 100, -1], [10, 30, 50, 100, "All"]],
                pageLength: 10,
                order: defaultOrder, // Apply default ordering if specified, otherwise no ordering
                dom: enableExport ? 'Blfrtip' : 'lfrtip', // B=buttons, l=length, f=filter, r=processing, t=table, i=info, p=pagination
                buttons: enableExport ? [
                    {
                        extend: 'csv',
                        title: exportFileName,
                        exportOptions: {
                            columns: ':visible:not(.no-export)'
                        }
                    },
                    {
                        extend: 'excel',
                        title: exportFileName,
                        exportOptions: {
                            columns: ':visible:not(.no-export)'
                        }
                    }
                ] : []
            };

            const table = new DataTable(tableElement, dtOptions);

            // ✅ Handle clicks on #select-all and individual row checkboxes
            wrapper.addEventListener('click', function(e) {
                const target = e.target;

                if (target.id === 'select-all') {
                    const isChecked = target.checked;

                    wrapper.querySelectorAll('tbody .row-checkbox').forEach(cb => {
                        cb.checked = isChecked;
                        cb.dispatchEvent(new Event('change'))
                    });
                }

                if (target.id === 'select-all' || target.classList.contains('row-checkbox')) {
                    if (typeof window[checkboxCallback] === 'function') {
                        const tableElement = document.getElementById(tableId)
                        window[checkboxCallback](tableElement);
                    }
                }
            });
        });
    </script>
@endpush
