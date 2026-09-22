<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Resource modules that get the standard CRUD permission set
     * (view, create, update, delete) generated automatically.
     */
    protected array $resources = [
        'suppliers',
        'cars',
        'customers',
        'agents',
        'orders',
        'batches',
        'container_openers',
        'service_providers',
        'expenses',
        'car_expenses',
        'documents',
        'invoices',
        'users',
        'settings',
        'car_media',
        'general_media',
        'contacts',
        'pre_order_cars',
        'pre_order_car_requests',
        'tags',
    ];

    /**
     * Permissions that don't follow the standard CRUD pattern, grouped by
     * the financial/reporting modules described in the requirements.
     */
    protected array $extraPermissions = [
        // Customer payments
        'customer_payments.view',
        'customer_payments.create',
        'customer_payments.update',
        'customer_payments.delete',

        // Supplier payments
        'supplier_payments.view',
        'supplier_payments.create',
        'supplier_payments.update',
        'supplier_payments.delete',

        // Agent transactions / commissions / ledger
        'agent_transactions.view',
        'agent_transactions.create',
        'agent_transactions.update',
        'agent_transactions.delete',
        'agent_transactions.approve',

        // Treasury (company-wide cash movements)
        'treasury.view',
        'treasury.create',

        // Orders - status workflow & scoped visibility
        'orders.view_assigned', // agent: only orders for their own customers
        'orders.change_status',

        // Cars - cost visibility is a SEPARATE, narrower permission than
        // cars.view. Per the explicit requirement: "مساعد مدير النظام
        // (Admin): صلاحية كاملة على جميع أجزاء النظام ما عدا أسعار الشراء
        // والشحن والفائدة لكل سيارة" — i.e. admin gets full CRUD on cars
        // (cars.view/create/update/delete) but NOT cost visibility.
        // Only super-admin holds cars.view_cost.
        'cars.view_cost',

        // Customers/agents scoped visibility
        'customers.view_assigned', // agent: only their own customers
        'customer_payments.view_own', // agent: only payments for their own customers
        'agent_transactions.view_own', // agent: only their own ledger entries

        // Dashboard & reports
        'dashboard.view',
        'reports.view',
        'reports.export',
        'reports.view_profit', // profit/loss figures - super-admin only, same reasoning as cars.view_cost

        // Roles & permissions management
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'api';

        $permissionNames = $this->extraPermissions;

        foreach ($this->resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissionNames[] = "{$resource}.{$action}";
            }
        }

        foreach (array_unique($permissionNames) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // ------------------------------------------------------------------
        // Roles
        // ------------------------------------------------------------------
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => $guard]);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $agent = Role::firstOrCreate(['name' => 'agent', 'guard_name' => $guard]);

        // super-admin: everything, including role/permission management and user management.
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        // admin: full operational access, EXCLUDING role/permission
        // management, destructive user deletion, AND cost/profit
        // visibility (purchase prices, shipping costs, per-car profit) —
        // per the explicit requirement that "Admin" sees everything
        // EXCEPT purchase/shipping costs and profit figures, which are
        // reserved for "Super Admin" only.
        $adminPermissions = Permission::where('guard_name', $guard)
            ->where('name', 'not like', 'roles.%')
            ->whereNotIn('name', [
                'users.delete',
                'cars.view_cost',
                'reports.view_profit',
            ])
            ->pluck('name')
            ->all();
        $admin->syncPermissions($adminPermissions);

        // agent: limited to their own customers, orders, and their own ledger.
        $agent->syncPermissions([
            'customers.view_assigned',
            'customers.create',
            'customers.update',
            'orders.view_assigned',
            'customer_payments.create',
            'cars.view',
            'orders.change_status',
            'orders.update',
            'orders.create',
            'agent_transactions.view_own',
            'customer_payments.view_own',
            
            
        ]);

        

        // ------------------------------------------------------------------
        // Default super-admin user, only created if it doesn't exist yet.
        // IMPORTANT: change this password immediately after first deploy.
        // ------------------------------------------------------------------
        $user = User::firstOrCreate(
            ['email' => 'superadmin@zaki.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole($superAdmin);
        }
    }
}
