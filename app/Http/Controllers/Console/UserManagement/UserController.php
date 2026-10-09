<?php

namespace App\Http\Controllers\Console\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $users = User::whereNot('id', Auth::id())->with('roles');

            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('roles', function ($user) {
                    return $user->roles->pluck('name')->join(', ');
                })
                ->addColumn('action', function ($user) {
                    $id = $user->id;
                    return '
                        <button onclick="setDataInAddEditOrViewUserCanvas(this)" class="btn btn-sm btn-warning me-1" data-id="' . $id . '" data-mode="edit">Edit</button>
                        <button onclick="setDataInAddEditOrViewUserCanvas(this)" class="btn btn-sm btn-info me-1" data-id="' . $id . '" data-mode="view">View</button>
                        <button onclick="deleteUser(event, this)" class="btn btn-sm btn-danger me-1" data-id="' . $id . '">Delete</button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $roles = Role::all();
        return view('console.user-management.user.index', compact('roles'));
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->first(), // if using spatie or similar
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $request->user_id,
            'role' => 'required|string|exists:roles,name',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'This email is already in use.',
            'role.required' => 'User role is required.',
            'role.exists' => 'Selected role does not exist.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $user->syncRoles([$request->role]);
            DB::commit();

            return response()->json(['success' => true, 'message' => 'User added successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Error saving user: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_id' => 'exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|exists:users,email',
            'role' => 'required|string|exists:roles,name',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            $user->syncRoles([$request->role]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'User updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $user = User::findOrFail($id);

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 403);
        }

        try {
            DB::beginTransaction();
            $user->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()], 500);
        }
    }
}
