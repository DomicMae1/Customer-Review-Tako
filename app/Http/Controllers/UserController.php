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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->can('user.view')) {
            abort(403, 'Unauthorized access.');
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
        $currentUser = Auth::user();
        if (!$currentUser->can('user.create')) {
            abort(403, 'Unauthorized access.');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'NIK' => ['required', 'digits:16'],
            'uid' => ['required', 'digits:8', 'unique:users,uid'],
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        $defaultPassword = substr($validated['NIK'], -8);

        $user = User::create([
            'name' => $validated['name'],
            'NIK' => $validated['NIK'],
            'uid' => $validated['uid'],
            'email' => $validated['email'],
            'password' => Hash::make($defaultPassword),
            'id_perusahaan' => $validated['id_perusahaan'] ?? null,
        ]);

        Log::info('Request Data:', $request->except(['password', 'password_confirmation']));

        $role = Role::findOrFail($validated['role']);
        $user->assignRole($role);

        return redirect()
            ->route('users.index')
            ->with('message', 'User created successfully.');
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
        $currentUser = Auth::user();
        if (!$currentUser->can('user.update')) {
            abort(403, 'Unauthorized access.');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'uid' => ['required', 'digits:8', 'unique:users,uid,' . $user->id],
            'NIK' => ['required', 'digits:16', 'unique:users,NIK,' . $user->id],
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        try {
            $data = [
                'name' => $validated['name'],
                'uid' => $validated['uid'],
                'NIK' => $validated['NIK'],
                'email' => $validated['email'],
            ];

            /*
            * Jika NIK berubah, password otomatis diganti
            * menjadi 8 digit terakhir dari NIK baru.
            */
            if ($user->NIK !== $validated['NIK']) {
                $defaultPassword = substr($validated['NIK'], -8);

                $data['password'] = Hash::make($defaultPassword);
            }

            $role = Role::findOrFail($validated['role']);

            if ($role->name === 'marketing' || $role->name === 'user') {
                $data['id_perusahaan'] = $validated['id_perusahaan'];
            } else {
                $data['id_perusahaan'] = null;
            }

            $user->update($data);
            $user->syncRoles([$role->name]);

            return redirect()
                ->route('users.index')
                ->with('message', 'User updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Failed to update user: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $currentUser = Auth::user();
        if (!$currentUser->can('user.delete')) {
            abort(403, 'Unauthorized access.');
        }
        $user->delete();
        return redirect()->route('users.index')->with('message', 'User deleted successfully.');
    }

    public function importCsv(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser->can('user.import')) {
            abort(403, 'Unauthorized access.');
        }
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        $content = file_get_contents($path);

        if (!$content) {
            return back()->withErrors([
                'csv_file' => 'File kosong atau tidak bisa dibaca.',
            ]);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            return back()->withErrors([
                'csv_file' => 'File harus memiliki header dan minimal 1 baris data.',
            ]);
        }

        $delimiter = $this->detectDelimiter($lines[0]);

        $header = str_getcsv($lines[0], $delimiter);
        $header = array_map(function ($value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return strtolower(trim($value));
        }, $header);

        $requiredHeaders = ['uid', 'nama_lengkap', 'nik', 'perusahaan'];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!in_array($requiredHeader, $header)) {
                return back()->withErrors([
                    'csv_file' => 'Header file wajib berisi: uid, nama_lengkap, nik, perusahaan.',
                ]);
            }
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::connection('tako-perusahaan')->transaction(function () use (
            $lines,
            $delimiter,
            $header,
            &$imported,
            &$updated,
            &$skipped,
            &$errors
        ) {
            foreach (array_slice($lines, 1) as $index => $line) {
                $rowNumber = $index + 2;

                if (trim($line) === '') {
                    continue;
                }

                $row = str_getcsv($line, $delimiter);

                if (count($row) !== count($header)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: jumlah kolom tidak sesuai.";
                    continue;
                }

                $data = array_combine($header, $row);

                $uid = trim($data['uid'] ?? '');
                $namaLengkap = trim($data['nama_lengkap'] ?? '');
                $nik = trim($data['nik'] ?? '');
                $namaPerusahaan = trim($data['perusahaan'] ?? '');

                if (!$uid || !$namaLengkap || !$nik || !$namaPerusahaan) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: uid, nama_lengkap, nik, dan perusahaan wajib diisi.";
                    continue;
                }

                $perusahaan = Perusahaan::firstOrCreate(
                    [
                        'nama_perusahaan' => $namaPerusahaan,
                    ],
                    [
                        'notify_1' => null,
                        'notify_2' => null,
                        'data' => null,
                        'id_domain' => null,
                    ]
                );

                $email = strtolower(Str::slug($uid)) . '@gmail.com';

                $user = User::where('uid', $uid)->first();

                if ($user) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: user dengan UID {$uid} sudah terdaftar.";
                    continue;
                }

                $nikOnlyNumber = preg_replace('/[^0-9]/', '', $nik);

                if (strlen($nikOnlyNumber) < 8) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: NIK harus memiliki minimal 8 angka.";
                    continue;
                }

                $passwordPlain = substr($nikOnlyNumber, -8);

                User::create([
                    'name' => $namaLengkap,
                    'uid' => $uid,
                    'NIK' => $nik,
                    'email' => $email,
                    'password' => Hash::make($passwordPlain),
                    'id_perusahaan' => $perusahaan->id,
                ]);

                $imported++;
            }
        });

        return back()->with([
            'success' => "Import selesai. {$imported} user baru ditambahkan, {$updated} user diperbarui, {$skipped} baris dilewati.",
            'import_errors' => $errors,
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser->can('user.reset-password')) {
            abort(403, 'Unauthorized access.');
        }
        try {
            if (!$user->NIK) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'NIK' => 'NIK user belum tersedia.',
                    ]);
            }

            $nikOnlyNumber = preg_replace('/\D/', '', $user->NIK);

            if (strlen($nikOnlyNumber) < 8) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'NIK' => 'NIK user kurang dari 6 digit.',
                    ]);
            }

            $defaultPassword = substr($nikOnlyNumber, -8);

            $user->update([
                'password' => Hash::make($defaultPassword),
            ]);

            return redirect()
                ->back()
                ->with('message', 'Password berhasil direset.');
        } catch (\Throwable $th) {
            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Gagal reset password: ' . $th->getMessage(),
                ]);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [
            "\t" => substr_count($line, "\t"),
            "," => substr_count($line, ","),
            ";" => substr_count($line, ";"),
        ];

        arsort($delimiters);

        return array_key_first($delimiters);
    }
}
