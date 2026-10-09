<?php

namespace App\Http\Controllers\Console\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RolesAndPermissionsController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $roles = Role::with('permissions')
                ->get();

            return DataTables::of($roles)
                ->addIndexColumn()
                ->addColumn('permissions', function ($row) {
                    return $row->permissions->pluck('name')->join(', ');
                })
                ->addColumn('action', function ($row) {
                    if($row->name==='Admin'){
                        return '';
                    }
                    return '<div class="d-inline-flex gap-1">
                        <a href="' . route('console.userManagement.roles.edit', ['id' => $row->id]) . '" class="btn btn-sm btn-warning">Edit</a>
                        <a href="javascript:;" class="btn btn-sm btn-danger delete-role" data-id="' . $row->id . '">Delete</a>
                    </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('console.user-management.role.index');
    }

    public function create()
    {
        $permissions = Permission::all();
        return view('console.user-management.role.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
        ]);

        $permissionNames = Permission::whereIn('id', $request->permissions ?? [])->pluck('id');

        $role->syncPermissions($permissionNames);

        return to_route('console.userManagement.roles.index')->with('success', 'Role created and permissions assigned successfully.');
    }

    public function edit(Request $request)
    {
        $role = Role::findOrFail($request->id);
        $permissions = Permission::all();
        return view('console.user-management.role.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($id);

        $role->update([
            'name' => $request->name,
        ]);

        $permissionNames = Permission::whereIn('id', $request->permissions)->pluck('id');

        $role->syncPermissions($permissionNames);

        return to_route('console.userManagement.roles.index')->with('success', 'Role updated successfully with permissions.');
    }

    public function destroy(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $role = Role::findOrFail($id);

        // Check if role is being used by any users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role. It is currently assigned to users.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }
}
