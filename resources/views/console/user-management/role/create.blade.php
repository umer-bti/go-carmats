@extends('console.layout.app')

@section('title', 'Add Role')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Add New Role</h5>
                <a href="{{ route('console.userManagement.roles.index') }}" class="btn btn-sm btn-secondary">
                    Back
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('console.userManagement.roles.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Role Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Enter role name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Permissions</label>
                        <div class="row">
                            @foreach ($permissions as $permission)
                                <div class="col-md-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}">
                                        <label class="form-check-label" for="perm_{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Create
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
