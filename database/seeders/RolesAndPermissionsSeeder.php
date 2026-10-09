<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view orders', 'edit orders', 'delete orders',
            'view batches', 'create batches', 'edit batches', 'delete batches',
            'view products', 'create products', 'edit products', 'delete products',
            'view Tracking', 'create Tracking', 'edit Tracking', 'delete Tracking',
            'view Returns', 'edit Returns', 'delete Returns',
            'view replacements', 'create replacements', 'delete replacements', 'print replacements',
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles', 'delete roles',
            'view labels', 'create labels',
            'view shipmate',
            'view stitchers', 'create stitchers', 'edit stitchers', 'delete stitchers',
            'view prestock', 'create prestock', 'edit prestock', 'delete prestock',
            'view collection', 'create collection', 'edit collection', 'delete collection',
            'view reports', 'export reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);

        }



        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions(Permission::all());

         // Assign the admin role to a specific user (e.g., the first user)

        $user = User::where('email', 'admin@admin.com')->first();

        if ($user) {
            $user->assignRole($adminRole);
        }
    }
}
