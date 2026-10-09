@extends('console.layout.app')

@section('title', 'User Management')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between border-none mb-5">
                <div>
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <button class="btn btn-primary my-3" style="float:right" onclick="setDataInAddEditOrViewUserCanvas(this)">
                    Add User
                </button>
            </div>
            <x-datatable id="usersTable" ajax="{{ route('console.userManagement.users.index') }}" :columns="[
                [
                    'data' => 'DT_RowIndex',
                    'name' => 'DT_RowIndex',
                    'label' => '#',
                    'orderable' => false,
                    'searchable' => false,
                ],
                ['data' => 'name', 'name' => 'name', 'label' => 'Name'],
                ['data' => 'email', 'name' => 'email', 'label' => 'Email'],
                ['data' => 'roles', 'name' => 'roles', 'label' => 'Roles'],
                [
                    'data' => 'action',
                    'name' => 'action',
                    'label' => 'Action',
                    'orderable' => false,
                    'searchable' => false,
                ],
            ]"
                :filters="[]" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function deleteUser(event, element) {
            event.preventDefault();
            const id = element.getAttribute('data-id');

            requestAccessPassword({
                title: 'Delete User',
                onVerified(password) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'You won\'t be able to revert this!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }

                        fetch("{{ route('console.userManagement.users.destroy', ':id') }}".replace(':id', id), {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ password })
                            })
                            .then(parseProtectedDeleteResponse)
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Deleted!', data.message, 'success');
                                    $('#usersTable').DataTable().ajax.reload();
                                } else {
                                    Swal.fire('Warning!', data.message, 'warning');
                                }
                            })
                            .catch(error => showProtectedDeleteError(error));
                    });
                }
            });
        }

        function setUserCanvasState(show = true) {
            const el = document.getElementById('addEditOrViewUserCanvas');
            if (el) bootstrap.Offcanvas.getOrCreateInstance(el)[show ? 'show' : 'hide']();
        }
    </script>
@endpush

@push('partials')
    @include('console.user-management.user.partials.add-edit-or-view-user-canvas')
@endpush
