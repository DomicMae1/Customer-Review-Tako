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
            'NIK' => 'nullable|string|max:255',
            'uid' => 'nullable|string|max:255|unique:users,uid',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'NIK' => $request->NIK,
            'uid' => $request->uid,
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
            'uid' => 'nullable|string|max:255|unique:users,uid,' . $user->id,
            'NIK' => 'nullable|string|max:255|unique:users,NIK,' . $user->id,
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,id',
            'id_perusahaan' => 'nullable|exists:perusahaan,id',
        ]);

        try {
            $data = [
                'name' => $request->name,
                'uid' => $request->uid,
                'NIK' => $request->NIK,
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
        $user->delete();
        return redirect()->route('users.index')->with('message', 'User deleted successfully.');
    }

    public function importCsv(Request $request)
    {
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
