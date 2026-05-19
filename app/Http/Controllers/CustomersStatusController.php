<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAttach;
use App\Models\Customers_Status;
use App\Models\Perusahaan;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\DB;

class CustomersStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $customerId = $request->query('customer_id');

        if (!$customerId) {
            return response()->json(['error' => 'Customer ID is required.'], 400);
        }

        $status = Customers_Status::with([
            'user',
            'status1Approver',
            'status2Approver',
            'status3Approver',
            'status4Approver',
        ])->where('id_Customer', $customerId)->first();

        if (!$status) {
            return response()->json(['message' => 'Status belum tersedia.'], 404);
        }

        $statusData = $status->toArray();
        $statusData['nama_user'] = $status->user?->name ?? null;
        $statusData['status_1_by_name'] = $status->status1Approver?->name ?? null;
        $statusData['status_2_by_name'] = $status->status2Approver?->name ?? null;
        $statusData['status_3_by_name'] = $status->status3Approver?->name ?? null;
        $statusData['status_4_by_name'] = $status->status4Approver?->name ?? null;

        return response()->json($statusData);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Customers_Status $customers_Status)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customers_Status $customers_Status)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customers_Status $customers_Status)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customers_Status $customers_Status)
    {
        //
    }

    private function nullableInt($value): ?int
    {
        if (
            $value === null ||
            $value === '' ||
            $value === 'null' ||
            $value === 'undefined'
        ) {
            return null;
        }

        return (int) $value;
    }

    public function submit(Request $request)
    {

        $request->validate([
            'customer_id' => 'required|exists:tako-perusahaan.customers_statuses,id_Customer',
            'keterangan' => 'nullable|string',
            'attach_path'         => 'nullable|string',
            'attach_filename'     => 'nullable|string',
            'submit_1_timestamps' => 'nullable|date',
            'status_1_timestamps' => 'nullable|date',
            'status_2_timestamps' => 'nullable|date',
        ]);

        $status = Customers_Status::where('id_Customer', $request->customer_id)->first();
        if (!$status) return back()->with('error', 'Data status customer tidak ditemukan.');

        $customer = $status->customer;

        $firstCustomerId = \App\Models\Customer::min('id');  
        $isFirstCustomer = $customer->id == $firstCustomerId;

        // 1. Ambil Info Perusahaan untuk Folder Name
        $idPerusahaan = $request->input('id_perusahaan');
        $perusahaan = Perusahaan::find($idPerusahaan);

        // Default slug jika perusahaan tidak ketemu
        $companySlug = 'general';
        $emailsToNotify = [];

        if ($perusahaan) {
            $companySlug = Str::slug($perusahaan->nama_perusahaan);

            if (!empty($perusahaan->notify_1)) {
                $emailsToNotify = explode(',', $perusahaan->notify_1);
            }
        }

        $status = Customers_Status::where('id_Customer', $request->customer_id)->first();

        if (!$status) {
            return back()->with('error', 'Data status customer tidak ditemukan.');
        }

        $user = Auth::user();
        $userId = $user->id;
        $rawRole  = strtolower($user->getRoleNames()->first());

        $roleMap = [
            'marketing' => 'user',
            'user'      => 'user',
            'manager'   => 'manager',
            'direktur'  => 'direktur',
            'director'  => 'direktur',
            'lawyer'    => 'lawyer',
            'auditor'   => 'auditor',
        ];

        $role = $roleMap[$rawRole] ?? $rawRole;
        $now = Carbon::now();

        $triggerRoles = ['user', 'manager', 'direktur'];

        $problematicCustomer = null;
        $problematicStatus = null;

        // Cek apakah User yang submit termasuk role tersebut
        if (in_array($role, $triggerRoles)) {

            $potentialDuplicates = Customer::with('perusahaan') // Only eager load same-db relations
                ->whereKeyNot($customer->id)
                ->where(function ($q) use ($customer) {
                    $q->where('no_npwp', $customer->no_npwp)
                        ->when($customer->no_npwp_16, fn($sq) => $sq->orWhere('no_npwp_16', $customer->no_npwp_16));
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $problematicCustomer = null;

            // 2. Loop and check status manually
            foreach ($potentialDuplicates as $duplicate) {
                // Explicitly use the correct model and connection
                $dupStatus = \App\Models\Customers_Status::on('tako-perusahaan')
                    ->where('id_Customer', $duplicate->id)
                    ->first();

                if (!$dupStatus) continue;

                // Check for issues
                $isRejected = strtolower($dupStatus->status_3 ?? '') === 'rejected';
                $hasAuditorNote = !empty($dupStatus->status_4_keterangan)
                    && $dupStatus->status_4_keterangan != '-'
                    && trim($dupStatus->status_4_keterangan) != '';

                if ($isRejected || $hasAuditorNote) {
                    // We found a problematic one!
                    // Manually attach the status to the duplicate object so the email view can use it
                    $duplicate->setRelation('status', $dupStatus);
                    $problematicCustomer = $duplicate;
                    break;
                }
            }

            $problematicStatus = $problematicCustomer?->status;

            // Jika ditemukan data lama yang bermasalah, KIRIM EMAIL
            if ($problematicCustomer) {

                $emailsToNotify = [];
                if ($perusahaan && !empty($perusahaan->notify_1)) {
                    $emailsToNotify = explode(',', $perusahaan->notify_1);
                }

                // Fallback (Jaga-jaga jika email kosong)
                if (empty($emailsToNotify)) {
                    $emailsToNotify = ['default@internal-perusahaan.com'];
                }

                $validEmails = collect($emailsToNotify)
                    ->map(fn($email) => trim($email))
                    ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->toArray();

                if (!empty($validEmails)) {
                    Log::info("Mengirim Alert Duplikat NPWP (Oleh: $role) ke:", $validEmails);

                    try {
                        // GANTI CLASS INI
                        Mail::to($validEmails)->send(new \App\Mail\CustomerAlert(
                            $user,                                                      // User yang submit
                            $customer,                                                  // Customer baru
                            $problematicCustomer,                                       // Customer lama
                            $problematicCustomer->status,
                        ));
                    } catch (\Exception $e) {
                        Log::error("Gagal kirim email alert duplikat: " . $e->getMessage());
                    }
                }
            }
        }

        if ($request->filled('submit_1_timestamps')) $status->submit_1_timestamps = $request->input('submit_1_timestamps');
        if ($request->filled('status_1_timestamps')) {
            $status->status_1_timestamps = $request->input('status_1_timestamps');
            $status->status_1_by = $userId;
        }
        if ($request->filled('status_2_timestamps')) {
            $status->status_2_timestamps = $request->input('status_2_timestamps');
            $status->status_2_by = $userId;
        }
        $isDirekturCreator = ($customer->id_user === $userId && $role === 'direktur');
        $isManagerCreator = ($customer->id_user === $userId && $role === 'manager');

        $finalFilename = $request->input('attach_filename');
        $finalPath = $request->input('attach_path');

        if (!in_array($role, ['user', 'manager', 'direktur', 'lawyer', 'auditor'])) {
            $finalFilename = null;
            $finalPath = null;
        }

        switch ($role) {
            case 'user':
                $status->submit_1_timestamps = $now;
                if ($finalFilename && $finalPath) {
                    $status->submit_1_nama_file = $finalFilename;
                    $status->submit_1_path = $finalPath;
                }

                // 🔹 Kirim email hanya jika perusahaan TIDAK punya manager
                // if ($perusahaan && !$perusahaan->hasManager()) {
                //     if (!empty($perusahaan->notify_1)) {
                //         $emailsToNotify = explode(',', $perusahaan->notify_1);
                //     }

                //     if (!empty($emailsToNotify)) {
                //         try {
                //             Mail::to($emailsToNotify)->send(new \App\Mail\CustomerSubmittedMail($customer));
                //         } catch (\Exception $e) {
                //             Log::error("Gagal kirim email lawyer (tanpa manager): " . $e->getMessage());
                //         }
                //     }
                // }

                break;

            case 'manager':
                $status->status_1_by = $userId;
                $status->status_1_timestamps = $now;
                $status->status_1_keterangan = $request->keterangan;
                if ($finalFilename && $finalPath) {
                    $status->status_1_nama_file = $finalFilename;
                    $status->status_1_path = $finalPath;
                }
                if ($isManagerCreator) {
                    if (empty($status->submit_1_timestamps)) {
                        $status->submit_1_timestamps = $now;
                    }
                }
                // if ($perusahaan && $perusahaan->hasManager()) {
                //     if (!empty($perusahaan->notify_1)) {
                //         $emailsToNotify = explode(',', $perusahaan->notify_1);
                //     }

                //     if (!empty($emailsToNotify)) {
                //         try {
                //             Mail::to($emailsToNotify)->send(new \App\Mail\CustomerSubmittedMail($customer));
                //         } catch (\Exception $e) {
                //             Log::error("Gagal kirim email lawyer (setelah manager): " . $e->getMessage());
                //         }
                //     }
                // }
                break;

            case 'direktur':
                $status->status_2_by = $userId;
                $status->status_2_timestamps = $now;
                $status->status_2_keterangan = $request->keterangan;
                if ($finalFilename && $finalPath) {
                    $status->status_2_nama_file = $finalFilename;
                    $status->status_2_path = $finalPath;
                }

                if ($isDirekturCreator) {
                    if (empty($status->submit_1_timestamps)) {
                        $status->submit_1_timestamps = $now;
                    }

                    if (empty($status->status_1_timestamps)) {
                        $status->status_1_timestamps = $now;
                        $status->status_1_by = $userId;
                    }
                }
                break;

            case 'lawyer':
                $status->status_3_by = $userId;
                $status->status_3_timestamps = $now;
                $status->status_3_keterangan = $request->keterangan;
                if ($finalFilename && $finalPath) {
                    $status->submit_3_nama_file = $finalFilename;
                    $status->submit_3_path = $finalPath;
                }

                if ($request->has('status_3')) {
                    $validStatuses = ['approved', 'rejected'];
                    $statusValue = strtolower($request->status_3);

                    if (in_array($statusValue, $validStatuses)) {
                        $status->status_3 = $statusValue;
                    }

                    if ($statusValue === 'rejected') {
                        $validEmails = collect($emailsToNotify)
                            ->map(fn($email) => trim($email))
                            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                            ->unique()
                            ->toArray();

                        Log::info('Akan mengirim email ke:', $validEmails);

                        $customer = $status->customer;

                        if (!empty($validEmails)) {
                            Mail::to($validEmails)->send(new \App\Mail\StatusRejectedMail($status, $user, $customer));
                        } else {
                            Mail::to('default@example.com')->send(new \App\Mail\StatusRejectedMail($status, $user, $customer));
                        }
                    }
                }
                break;

            case 'auditor':
                $status->status_4_by = $userId;
                $status->status_4_timestamps = $now;
                $status->status_4_keterangan = $request->keterangan;
                if ($finalFilename && $finalPath) {
                    $status->status_4_nama_file = $finalFilename;
                    $status->status_4_path = $finalPath;
                }
                break;

            default:
                return back()->with('error', 'Role tidak dikenali.');
        }

        if ($request->filled('status_3') && $role !== 'lawyer') {
            $status->status_3 = $request->input('status_3');

            $status->status_3_by =
                $problematicStatus?->status_3_by
                ?? $this->nullableInt($request->input('status_3_by'));

            if ($problematicStatus?->status_3_timestamps) {
                $status->status_3_timestamps = $problematicStatus->status_3_timestamps;
            } else {
                $status->status_3_timestamps = $now;
            }

            if ($request->filled('status_3_keterangan')) {
                $status->status_3_keterangan = $request->input('status_3_keterangan');
            } elseif ($problematicStatus?->status_3_keterangan) {
                $status->status_3_keterangan = $problematicStatus->status_3_keterangan;
            }

            if ($request->filled('submit_3_path')) {
                $status->submit_3_path = $request->input('submit_3_path');
                $status->submit_3_nama_file = basename($request->input('submit_3_path'));
            } elseif ($problematicStatus?->submit_3_path) {
                $status->submit_3_path = $problematicStatus->submit_3_path;
                $status->submit_3_nama_file = $problematicStatus->submit_3_nama_file;
            }
        }

        if (($request->filled('status_4_keterangan') || $request->filled('status_4_path') || $problematicStatus?->status_4_keterangan) && $role !== 'auditor') {
            $status->status_4_by =
                $problematicStatus?->status_4_by
                ?? $this->nullableInt($request->input('status_4_by'));

            if ($request->filled('status_4_keterangan')) {
                $status->status_4_keterangan = $request->input('status_4_keterangan');
            } elseif ($problematicStatus?->status_4_keterangan) {
                $status->status_4_keterangan = $problematicStatus->status_4_keterangan;
            }

            if ($request->filled('status_4_path')) {
                $status->status_4_path = $request->input('status_4_path');
                $status->status_4_nama_file = basename($request->input('status_4_path'));
            } elseif ($problematicStatus?->status_4_path) {
                $status->status_4_path = $problematicStatus->status_4_path;
                $status->status_4_nama_file = $problematicStatus->status_4_nama_file;
            }

            $status->status_4_timestamps = $problematicStatus?->status_4_timestamps ?? $now;
        }

        Log::info('Submit payload check', [
            'id_perusahaan' => $request->input('id_perusahaan'),
            'status_3_by' => $request->input('status_3_by'),
            'status_4_by' => $request->input('status_4_by'),
            'customer_id' => $request->input('customer_id'),
        ]);

        $status->save();

        if ($isFirstCustomer) {
            try {
                $auditorEmails = User::role('auditor')
                    ->whereNotNull('email')
                    ->pluck('email')
                    ->map(fn($email) => trim($email))
                    ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($auditorEmails)) {
                    Mail::to($auditorEmails)->send(new \App\Mail\NewCustomerAuditorMail($customer, $status, $user));
                }
            } catch (\Exception $e) {
                Log::error('Gagal kirim email customer baru ke auditor: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Data berhasil disubmit.');
    }
}
