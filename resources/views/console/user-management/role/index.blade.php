@extends('console.layout.app')

@section('title', 'Roles')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Roles</h4>
            <a href="{{ route('console.userManagement.roles.create') }}" class="btn btn-primary">
                Add Role
            </a>
        </div>

        <div class="card">
            <div class="card-datatable pt-0 m-5">
                <table id="roles-table" class="datatables-basic table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Role Name</th>
                            <th>Guard</th>
                            <th>Permissions</th>
                            <th class="cell-fit">Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('themes/console/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script>
        $(function() {
            $('#roles-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('console.userManagement.roles.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'guard_name',
                        name: 'guard_name'
                    },
                    {
                        data: 'permissions',
                        name: 'permissions',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],
                responsive: true
            });

            // Handle delete role button click
            $(document).on('click', '.delete-role', function(e) {
                e.preventDefault();
                const roleId = $(this).data('id');

                requestAccessPassword({
                    title: 'Delete Role',
                    onVerified(password) {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'This will permanently delete the role.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel',
                            reverseButtons: true
                        }).then((result) => {
                            if (!result.isConfirmed) {
                                return;
                            }

                            fetch("{{ route('console.userManagement.roles.destroy', ':id') }}".replace(':id', roleId), {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ password })
                            })
                            .then(parseProtectedDeleteResponse)
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Deleted!', data.message, 'success');
                                    $('#roles-table').DataTable().ajax.reload();
                                } else {
                                    Swal.fire('Error!', data.message || 'Something went wrong.', 'error');
                                }
                            })
                            .catch(error => showProtectedDeleteError(error));
                        });
                    }
                });
            });
        });
    </script>
@endpush
