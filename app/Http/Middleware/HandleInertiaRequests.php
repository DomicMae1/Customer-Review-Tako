<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => function () use ($request) {
                    if (!$user = $request->user()) {
                        return null;
                    }

                    $user->load(['roles', 'perusahaan', 'companies']);

                    $perusahaan = $user->perusahaan;

                    if (!$perusahaan && $user->companies->isNotEmpty()) {
                        $roleNames = $user->roles->pluck('name')->toArray();

                        if (in_array('manager', $roleNames) || in_array('direktur', $roleNames)) {
                            $perusahaan = $user->companies->first();
                        }
                    }

                    return array_merge(
                        $user->toArray(),
                        [
                            'perusahaan' => $perusahaan,
                            'permissions' => $user->getAllPermissions()->pluck('name'),
                        ]
                    );
                },
            ],
            'company' => [
                'id' => session('company_id'),
                'name' => session('company_name'),
                'logo' => session('company_logo'), // sudah asset('storage/...') dari controller login
            ],

            // Admin company selector: daftar semua perusahaan dan pilihan aktif
            'all_companies' => function () use ($request) {
                $user = $request->user();
                if (!$user || !$user->hasRole('admin')) {
                    return [];
                }
                return \App\Models\Perusahaan::select('id', 'nama_perusahaan')
                    ->orderBy('nama_perusahaan')
                    ->get()
                    ->toArray();
            },
            'admin_active_company_id' => function () use ($request) {
                $user = $request->user();
                if (!$user || !$user->hasRole('admin')) {
                    return null;
                }
                return session('admin_active_company_id');
            },

            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
