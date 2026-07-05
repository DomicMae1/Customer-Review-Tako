<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->can('role.view')) {
            abort(403, 'Unauthorized access.');
        }

        $roles = Role::with('permissions')->where('name', '!=', 'admin')->get();

        // Hanya tampilkan dot-notation permissions di form UI.
        // Legacy permissions (format lama dengan '-') tetap ada di database dan
        // tidak terpengaruh — role yang sudah memilikinya tetap berfungsi normal.
        $permissions = Permission::all()
            ->filter(fn ($permission) => str_contains($permission->name, '.'))
            ->groupBy(function ($permission) {
                // Ambil prefix sebelum titik pertama sebagai nama grup
                return explode('.', $permission->name, 2)[0];
            })
            ->map(fn ($group) => $group->pluck('name')->values()->toArray())
            ->toArray();

        return Inertia::render('role/page', [
            'roles' => $roles,
            'permissions' => $permissions,
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('role.create')) {
            abort(403, 'Unauthorized access.');
        }
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('role-manager.index')->with('success', 'Role created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->can('role.update')) {
            abort(403, 'Unauthorized access.');
        }
        $role = Role::findOrFail($id);
        if ($role->name === 'admin') {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return redirect()->route('role-manager.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user->can('role.delete')) {
            abort(403, 'Unauthorized access.');
        }
        $role = Role::findOrFail($id);
        if ($role->name === 'admin') {
            abort(403, 'Unauthorized access.');
        }

        $role->delete();
        return redirect()->route('role-manager.index')->with('success', 'Role deleted successfully.');
    }
}
