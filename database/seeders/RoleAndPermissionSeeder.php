<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Merge legacy 'user' role into 'marketing' if both exist, or rename 'user' to 'marketing'
        try {
            $userRole = Role::where('name', 'user')->first();
            $marketingRole = Role::where('name', 'marketing')->first();

            if ($userRole && $marketingRole) {
                // Merge permissions
                $marketingRole->givePermissionTo($userRole->permissions);

                // Merge users in model_has_roles
                \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->where('role_id', $userRole->id)
                    ->update(['role_id' => $marketingRole->id]);

                // Delete 'user' role
                $userRole->delete();
            } elseif ($userRole) {
                // Rename user to marketing
                $userRole->update(['name' => 'marketing']);
            }

            // Sync/Merge in tako-perusahaan connection roles table if it exists
            $tpUserRole = \Illuminate\Support\Facades\DB::connection('tako-perusahaan')->table('roles')->where('name', 'user')->first();
            $tpMarketingRole = \Illuminate\Support\Facades\DB::connection('tako-perusahaan')->table('roles')->where('name', 'marketing')->first();
            if ($tpUserRole && $tpMarketingRole) {
                \Illuminate\Support\Facades\DB::connection('tako-perusahaan')->table('roles')->where('name', 'user')->delete();
            } elseif ($tpUserRole) {
                \Illuminate\Support\Facades\DB::connection('tako-perusahaan')->table('roles')->where('name', 'user')->update(['name' => 'marketing']);
            }

            // Update in perusahaan_user_roles table
            \Illuminate\Support\Facades\DB::connection('tako-perusahaan')
                ->table('perusahaan_user_roles')
                ->where('role', 'user')
                ->update(['role' => 'marketing']);
        } catch (\Throwable $e) {
            // Log or ignore errors
        }

        // 1. Existing Legacy Permissions (for compatibility)
        $legacyPermissions = [
            // Email Permissions
            'create-email-manager-master-customer',
            'create-email-direktur-master-customer',
            'create-email-lawyer-master-customer',

            // Manager Permissions
            'view-manager-master-customer',
            'create-manager-master-customer',

            // Direktur Permissions
            'view-direktur-master-customer',
            'create-direktur-master-customer',

            // Lawyer Permissions
            'view-lawyer-master-customer',
            'create-lawyer-master-customer',
        ];

        foreach ($legacyPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $models = [
            'master-customer',
        ];

        $actions = [
            'create',
            'update',
            'delete',
            'view',
        ];

        foreach ($models as $model) {
            foreach ($actions as $action) {
                $permissionName = "{$action}-{$model}";
                Permission::firstOrCreate(['name' => $permissionName]);
            }
        }

        // 2. New Standardized Permissions (Dot Notation)
        $newPermissions = [
            // Customer
            'customer.view',
            'customer.bank.view',
            'customer.create',
            'customer.update',
            'customer.delete',
            'customer.pdf',
            'customer.import',
            'customer.link.create',
            'customer.approve.manager',
            'customer.approve.direktur',
            'customer.approve.lawyer',
            'customer.approve.auditor',

            // Supplier
            'supplier.view',
            'supplier.bank.view',
            'supplier.create',
            'supplier.update',
            'supplier.delete',
            'supplier.pdf',
            'supplier.import',
            'supplier.link.create',
            'supplier.approve.manager',
            'supplier.approve.direktur',
            'supplier.approve.lawyer',
            'supplier.approve.auditor',

            // Perusahaan
            'perusahaan.view',
            'perusahaan.create',
            'perusahaan.update',
            'perusahaan.delete',

            // Users
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'user.import',
            'user.reset-password',

            // Roles
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
        ];

        foreach ($newPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 3. Setup Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $lawyerRole = Role::firstOrCreate(['name' => 'lawyer']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $marketingRole = Role::firstOrCreate(['name' => 'marketing']);
        $auditorRole = Role::firstOrCreate(['name' => 'auditor']);
        $direkturRole = Role::firstOrCreate(['name' => 'direktur']);

        // 4. Sync Permissions
        // Admin gets all permissions (legacy + new)
        $adminRole->syncPermissions(Permission::all());

        // Manager Permissions
        $managerPermissions = [
            // Legacy
            'create-master-customer',
            'view-master-customer',
            'view-manager-master-customer',
            'create-manager-master-customer',
            'create-email-manager-master-customer',
            // New
            'customer.view',
            'customer.create',
            'customer.update',
            'customer.approve.manager',
            'customer.link.create',
            // Supplier
            'supplier.view',
            'supplier.create',
            'supplier.update',
            'supplier.approve.manager',
            'supplier.link.create',
        ];
        $managerRole->syncPermissions(array_intersect(
            Permission::all()->pluck('name')->toArray(),
            $managerPermissions
        ));

        // Direktur Permissions
        $direkturPermissions = [
            // Legacy
            'create-master-customer',
            'view-master-customer',
            'view-direktur-master-customer',
            'create-direktur-master-customer',
            'create-email-direktur-master-customer',
            // New
            'customer.view',
            'customer.create',
            'customer.approve.direktur',
            'customer.link.create',
            // Supplier
            'supplier.view',
            'supplier.create',
            'supplier.approve.direktur',
            'supplier.link.create',
        ];
        $direkturRole->syncPermissions(array_intersect(
            Permission::all()->pluck('name')->toArray(),
            $direkturPermissions
        ));

        // Lawyer Permissions
        $lawyerPermissions = [
            // Legacy
            'view-master-customer',
            'view-lawyer-master-customer',
            'create-lawyer-master-customer',
            'create-email-lawyer-master-customer',
            // New
            'customer.view',
            'customer.approve.lawyer',
            // Supplier
            'supplier.view',
            'supplier.approve.lawyer',
        ];
        $lawyerRole->syncPermissions(array_intersect(
            Permission::all()->pluck('name')->toArray(),
            $lawyerPermissions
        ));

        // Marketing Permissions
        $marketingPermissions = [
            // Legacy
            'create-master-customer',
            'update-master-customer',
            'view-master-customer',
            // New
            'customer.view',
            'customer.create',
            'customer.update',
            // Supplier
            'supplier.view',
            'supplier.create',
            'supplier.update',
        ];
        $marketingRole->syncPermissions(array_intersect(
            Permission::all()->pluck('name')->toArray(),
            $marketingPermissions
        ));

        // Auditor Permissions
        $allPermissions = Permission::all()->pluck('name')->toArray();
        $auditorPermissions = [
            'customer.view',
            'customer.approve.auditor',
            'supplier.view',
            'supplier.approve.auditor',
        ];
        $legacyAuditor = array_filter($allPermissions, function ($perm) {
            return !str_contains($perm, '.') && $perm !== 'create-master-customer' && $perm !== 'delete-master-customer';
        });
        $auditorRole->syncPermissions(array_merge($legacyAuditor, $auditorPermissions));

        // Ensure all roles have view access to customer & supplier
        $viewMasterCustomerPermission = Permission::firstOrCreate(['name' => 'view-master-customer']);
        $newViewCustomerPermission = Permission::firstOrCreate(['name' => 'customer.view']);
        $newViewSupplierPermission = Permission::firstOrCreate(['name' => 'supplier.view']);

        foreach (Role::all() as $role) {
            if ($role->name === 'admin') {
                continue;
            }
            if (!$role->hasPermissionTo('view-master-customer')) {
                $role->givePermissionTo($viewMasterCustomerPermission);
            }
            if (!$role->hasPermissionTo('customer.view')) {
                $role->givePermissionTo($newViewCustomerPermission);
            }
            if (!$role->hasPermissionTo('supplier.view')) {
                $role->givePermissionTo($newViewSupplierPermission);
            }
        }
    }
}
