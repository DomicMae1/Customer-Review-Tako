<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierAttach;
use App\Models\SuppliersStatus;
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

class SuppliersStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $supplierId = $request->query('supplier_id');

        if (!$supplierId) {
            return response()->json(['error' => 'Supplier ID is required.'], 400);
        }

        $status = SuppliersStatus::with([
            'user',
            'status1Approver',
            'status2Approver',
            'status3Approver',
            'status4Approver',
        ])->where('id_Supplier', $supplierId)->first();

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
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(SuppliersStatus $suppliersStatus) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SuppliersStatus $suppliersStatus) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SuppliersStatus $suppliersStatus) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SuppliersStatus $suppliersStatus) {}

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

    private function hasExistingNpwpRegistration(Supplier $supplier): bool
    {
        $query = Supplier::whereKeyNot($supplier->id);

        if (!empty($supplier->uid)) {
            return $query->where('uid', $supplier->uid)->exists();
        }

        $noNpwp = trim((string) $supplier->no_npwp);
        $noNpwp16 = trim((string) $supplier->no_npwp_16);

        if ($noNpwp === '' && $noNpwp16 === '') {
            return false;
        }

        return $query->where(function ($q) use ($noNpwp, $noNpwp16) {
            if ($noNpwp !== '') {
                $q->orWhere('no_npwp', $noNpwp);
            }

            if ($noNpwp16 !== '') {
                $q->orWhere('no_npwp_16', $noNpwp16);
            }
        })->exists();
    }

    private function parseValidEmails(?string $rawEmails): array
    {
        if (empty($rawEmails)) {
            return [];
        }

        return collect(explode(',', $rawEmails))
            ->map(fn($email) => trim($email))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->toArray();
    }

    private function getCompanyRoleRecipientEmails(?int $companyId, string $role, bool $includeGlobalUsers = false): array
    {
        $query = User::role($role)
            ->whereNotNull('email');

        if ($companyId) {
            $query->where(function ($q) use ($companyId, $includeGlobalUsers) {
                $q->where('id_perusahaan', $companyId)
                    ->orWhereHas('companies', function ($companyQuery) use ($companyId) {
                        $companyQuery->where('perusahaan.id', $companyId);
                    });

                if ($includeGlobalUsers) {
                    $q->orWhereNull('id_perusahaan');
                }
            });
        } elseif (!$includeGlobalUsers) {
            return [];
        }

        return $query->pluck('email')
            ->map(fn($email) => trim($email))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->toArray();
    }

    private function getProblematicNpwpRecipientEmails(?Perusahaan $perusahaan, string $submitterRole): array
    {
        $rolesToNotify = match ($submitterRole) {
            'marketing', 'user' => ['manager', 'direktur', 'auditor'],
            'manager' => ['direktur', 'auditor'],
            'direktur' => ['auditor'],
            default => [],
        };

        $emails = collect($this->parseValidEmails($perusahaan?->notify_1));

        foreach ($rolesToNotify as $roleToNotify) {
            $emails = $emails->merge(
                $this->getCompanyRoleRecipientEmails(
                    $perusahaan?->id,
                    $roleToNotify,
                    $roleToNotify === 'auditor'
                )
            );
        }

        return $emails
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->toArray();
    }

    public function submit(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:tako-perusahaan.suppliers_statuses,id_Supplier',
            'keterangan' => 'nullable|string',
            'attach_path'         => 'nullable|string',
            'attach_filename'     => 'nullable|string',
            'submit_1_timestamps' => 'nullable|date',
            'status_1_timestamps' => 'nullable|date',
            'status_2_timestamps' => 'nullable|date',
        ]);

        $status = SuppliersStatus::where('id_Supplier', $request->supplier_id)->first();
        if (!$status) return back()->with('error', 'Data status supplier tidak ditemukan.');

        $supplier = $status->supplier;
        $wasSubmittedToWorkflow = !empty($status->submit_1_timestamps);
        $isFirstNpwpRegistration = !$this->hasExistingNpwpRegistration($supplier);

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

        $user = Auth::user();
        $userId = $user->id;
        $rawRole  = strtolower($user->getRoleNames()->first());

        $roleMap = [
            'marketing' => 'marketing',
            'user'      => 'marketing',
            'manager'   => 'manager',
            'direktur'  => 'direktur',
            'director'  => 'direktur',
            'lawyer'    => 'lawyer',
            'auditor'   => 'auditor',
        ];

        $role = $roleMap[$rawRole] ?? $rawRole;

        switch ($role) {
            case 'marketing':
                if (!$user->can('supplier.create') && !$user->can('supplier.update')) {
                    abort(403, 'Unauthorized. Lacking supplier creation or update permission.');
                }
                break;
            case 'manager':
                if (!$user->can('supplier.approve.manager')) {
                    abort(403, 'Unauthorized. Lacking manager approval permission.');
                }
                break;
            case 'direktur':
                if (!$user->can('supplier.approve.direktur')) {
                    abort(403, 'Unauthorized. Lacking direktur approval permission.');
                }
                break;
            case 'lawyer':
                if (!$user->can('supplier.approve.lawyer')) {
                    abort(403, 'Unauthorized. Lacking lawyer approval permission.');
                }
                break;
            case 'auditor':
                if (!$user->can('supplier.approve.auditor')) {
                    abort(403, 'Unauthorized. Lacking auditor approval permission.');
                }
                break;
            default:
                abort(403, 'Unauthorized. Role tidak dikenali.');
        }

        $now = Carbon::now();

        $triggerRoles = ['marketing', 'manager', 'direktur'];

        $problematicSupplier = null;
        $problematicStatus = null;

        // Cek apakah User yang submit termasuk role tersebut
        if (in_array($role, $triggerRoles)) {

            $potentialDuplicates = Supplier::with('perusahaan')
                ->whereKeyNot($supplier->id)
                ->where(function ($q) use ($supplier) {
                    $q->where('no_npwp', $supplier->no_npwp)
                        ->when($supplier->no_npwp_16, fn($sq) => $sq->orWhere('no_npwp_16', $supplier->no_npwp_16));
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $problematicSupplier = null;

            // 2. Loop and check status manually
            foreach ($potentialDuplicates as $duplicate) {
                $dupStatus = SuppliersStatus::on('tako-perusahaan')
                    ->where('id_Supplier', $duplicate->id)
                    ->first();

                if (!$dupStatus) continue;

                // Check for issues
                $isRejected = strtolower($dupStatus->status_3 ?? '') === 'rejected';
                $hasAuditorNote = !empty($dupStatus->status_4_keterangan)
                    && $dupStatus->status_4_keterangan != '-'
                    && trim($dupStatus->status_4_keterangan) != '';

                if ($isRejected || $hasAuditorNote) {
                    $duplicate->setRelation('status', $dupStatus);
                    $problematicSupplier = $duplicate;
                    break;
                }
            }

            $problematicStatus = $problematicSupplier?->status;

            // Jika ditemukan data lama yang bermasalah, KIRIM EMAIL
            if ($problematicSupplier) {
                $validEmails = $this->getProblematicNpwpRecipientEmails($perusahaan, $role);

                if (!empty($validEmails)) {
                    Log::info('Mengirim Alert Duplikat NPWP Supplier', [
                        'submitted_by_role' => $role,
                        'supplier_id' => $supplier->id,
                        'company_id' => $perusahaan?->id,
                        'recipient_emails' => $validEmails,
                    ]);

                    try {
                        Mail::to($validEmails)->send(new \App\Mail\SupplierAlert(
                            $user,                                                      // User yang submit
                            $supplier,                                                  // Supplier baru
                            $problematicSupplier,                                       // Supplier lama
                            $problematicSupplier->status,
                        ));
                    } catch (\Exception $e) {
                        Log::error("Gagal kirim email alert duplikat supplier: " . $e->getMessage());
                    }
                } else {
                    Log::warning('Alert Duplikat NPWP Supplier tidak dikirim karena tidak ada email penerima yang valid.', [
                        'submitted_by_role' => $role,
                        'supplier_id' => $supplier->id,
                        'company_id' => $perusahaan?->id,
                    ]);
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
        $isDirekturCreator = ($supplier->id_user === $userId && $role === 'direktur');
        $isManagerCreator = ($supplier->id_user === $userId && $role === 'manager');

        $finalFilename = $request->input('attach_filename');
        $finalPath = $request->input('attach_path');

        if (!in_array($role, ['marketing', 'manager', 'direktur', 'lawyer', 'auditor'])) {
            $finalFilename = null;
            $finalPath = null;
        }

        switch ($role) {
            case 'marketing':
                $status->submit_1_timestamps = $now;
                if ($finalFilename && $finalPath) {
                    $status->submit_1_nama_file = $finalFilename;
                    $status->submit_1_path = $finalPath;
                }
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

                        Log::info('Supplier rejected, sending emails to:', $validEmails);

                        if (!empty($validEmails)) {
                            Mail::to($validEmails)->send(new \App\Mail\SupplierStatusRejectedMail($status, $user, $supplier));
                        } else {
                            Mail::to('default@example.com')->send(new \App\Mail\SupplierStatusRejectedMail($status, $user, $supplier));
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

        Log::info('Supplier Submit payload check', [
            'id_perusahaan' => $request->input('id_perusahaan'),
            'status_3_by' => $request->input('status_3_by'),
            'status_4_by' => $request->input('status_4_by'),
            'supplier_id' => $request->input('supplier_id'),
        ]);

        $shouldNotifyAuditorForNewNpwp = !$wasSubmittedToWorkflow
            && !empty($status->submit_1_timestamps)
            && $isFirstNpwpRegistration;

        Log::info('Auditor email NPWP supplier decision', [
            'supplier_id' => $supplier->id,
            'supplier_uid' => $supplier->uid,
            'no_npwp' => $supplier->no_npwp,
            'no_npwp_16' => $supplier->no_npwp_16,
            'submitted_by_role' => $role,
            'was_submitted_to_workflow' => $wasSubmittedToWorkflow,
            'submit_1_timestamps' => $status->submit_1_timestamps,
            'is_first_npwp_registration' => $isFirstNpwpRegistration,
            'should_notify_auditor_for_new_npwp' => $shouldNotifyAuditorForNewNpwp,
        ]);

        $status->save();

        if ($shouldNotifyAuditorForNewNpwp) {
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
                    Log::info('Mengirim email auditor untuk NPWP supplier baru', [
                        'supplier_id' => $supplier->id,
                        'supplier_uid' => $supplier->uid,
                        'auditor_emails' => $auditorEmails,
                    ]);

                    Mail::to($auditorEmails)->send(new \App\Mail\NewSupplierAuditorMail($supplier, $status, $user));
                }
            } catch (\Exception $e) {
                Log::error('Gagal kirim email supplier baru ke auditor: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Data berhasil disubmit.');
    }
}
