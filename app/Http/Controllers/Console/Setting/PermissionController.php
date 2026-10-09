<?php

namespace App\Http\Controllers\Console\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function get(Role $role)
    {
        return response()->json([
            'permissions' => $role->permissions->pluck('id')
        ]);
    }
}
