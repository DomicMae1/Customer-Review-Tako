<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->hasRole('admin')) {
            abort(403, 'Unauthorized access. Only admin can access this page.');
        }

        $users = User::with('roles')->get();
        $roles = Role::all(['id', 'name']);

        return Inertia::render('auth/page', [
            'users' => $users,
            'roles' => $roles,
            'companies' => Perusahaan::select('id as id', 'nama_perusahaan')->get(),
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'id_perusahaan' => $request->id_perusahaan,
        ]);

        Log::info('Request Data:', $request->all());

        $role = Role::find($request->role);
        $user->assignRole($role);

        return redirect()->route('users.index')->with('message', 'User created successfully.');
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
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'password' => ['sometimes', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        try {
            $data = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $role = Role::findOrFail($request->role);

            if ($role->name === 'user') {
                $data['id_perusahaan'] = $request->id_perusahaan;
            } else {
                $data['id_perusahaan'] = null;
            }

            $user->update($data);
            $user->syncRoles([$role->name]);

            return redirect()->route('users.index')->with('message', 'User updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('message', 'User deleted successfully.');
    }
}
