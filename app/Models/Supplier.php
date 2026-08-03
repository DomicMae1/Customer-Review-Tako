<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Perusahaan;
use App\Models\SupplierAttach;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use SoftDeletes;

    protected $connection = 'tako-customer';
    protected $table = 'suppliers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_user',
        'id_perusahaan',
        'supplier_category',
        'kategori_usaha',
        'nama_perusahaan',
        'bentuk_badan_usaha',
        'jenis_perusahaan',
        'alamat_lengkap',
        'kota',
        'no_telp',
        'no_fax',
        'alamat_penagihan',
        'email',
        'website',
        'top',
        'status_perpajakan',
        'no_npwp',
        'no_npwp_16',
        'nib',
        'nama_pj',
        'no_ktp_pj',
        'no_telp_pj',
        'nama_personal',
        'jabatan_personal',
        'no_telp_personal',
        'email_personal',
    ];

    public function setNamaPjAttribute($value): void
    {
        $this->attributes['nama_pj'] = $value ?? '';
    }

    public function setNoKtpPjAttribute($value): void
    {
        $this->attributes['no_ktp_pj'] = $value ?? '';
    }

    public function setNoTelpAttribute($value): void
    {
        if (is_array($value)) {
            $phones = $this->normalizePhoneNumbers($value);
            $this->attributes['no_telp'] = empty($phones) ? null : json_encode($phones);
            return;
        }

        if ($value === null) {
            $this->attributes['no_telp'] = null;
            return;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            $this->attributes['no_telp'] = null;
            return;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $phones = $this->normalizePhoneNumbers($decoded);
            $this->attributes['no_telp'] = empty($phones) ? null : json_encode($phones);
            return;
        }

        $this->attributes['no_telp'] = $trimmed;
    }

    public function getNoTelpListAttribute(): array
    {
        return $this->normalizePhoneNumbers($this->attributes['no_telp'] ?? null);
    }

    public function getFormattedNoTelpAttribute(): string
    {
        return implode(', ', $this->no_telp_list);
    }

    public function getPrimaryNoTelpAttribute(): string
    {
        return $this->no_telp_list[0] ?? '';
    }

    public function setNoTelpPersonalAttribute($value): void
    {
        if (is_array($value)) {
            $phones = $this->normalizePhoneNumbers($value);
            $this->attributes['no_telp_personal'] = empty($phones) ? null : json_encode($phones);
            return;
        }

        if ($value === null) {
            $this->attributes['no_telp_personal'] = null;
            return;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            $this->attributes['no_telp_personal'] = null;
            return;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $phones = $this->normalizePhoneNumbers($decoded);
            $this->attributes['no_telp_personal'] = empty($phones) ? null : json_encode($phones);
            return;
        }

        $this->attributes['no_telp_personal'] = $trimmed;
    }

    public function getNoTelpPersonalListAttribute(): array
    {
        return $this->normalizePhoneNumbers($this->attributes['no_telp_personal'] ?? null);
    }

    public function getFormattedNoTelpPersonalAttribute(): string
    {
        return implode(', ', $this->no_telp_personal_list);
    }

    public function getPrimaryNoTelpPersonalAttribute(): string
    {
        return $this->no_telp_personal_list[0] ?? '';
    }

    protected static function booted()
    {
        static::creating(function ($supplier) {
            $existingUid = null;

            if (!empty($supplier->no_npwp) || !empty($supplier->no_npwp_16)) {
                 $existingUid = DB::connection('tako-customer')
                    ->table('suppliers')
                    ->whereNotNull('uid')
                    ->where(function($q) use ($supplier) {
                        if (!empty($supplier->no_npwp)) {
                            $q->orWhere('no_npwp', trim($supplier->no_npwp));
                        }
                        if (!empty($supplier->no_npwp_16)) {
                            $q->orWhere('no_npwp_16', trim($supplier->no_npwp_16));
                        }
                    })
                    ->orderBy('created_at', 'asc') 
                    ->value('uid');
            }

            if ($existingUid) {
                $supplier->uid = $existingUid;
                return; 
            }

            $prefix = now()->format('Ym');
            $uid = null;
            $maxAttempts = 50;
            $attempt = 0;

            do {
                $attempt++;
                $random = random_int(100000, 999999);
                $candidateUid = $prefix . $random;
                $exists = DB::connection('tako-customer')
                            ->table('suppliers')
                            ->where('uid', $candidateUid)
                            ->exists();
                
                if (!$exists) {
                    $uid = $candidateUid;
                }

                if ($attempt >= $maxAttempts) {
                    $uid = $prefix . random_int(10, 99) . time(); 
                }

            } while ($uid === null);

            $supplier->uid = $uid;
        });
    }

    /**
     * Relasi ke user pembuat (dari database tako-perusahaan).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Relasi ke perusahaan (dari database tako-perusahaan).
     */
    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'id_perusahaan');
    }

    /**
     * Relasi ke lampiran dokumen supplier.
     */
    public function attachments()
    {
        return $this->hasMany(SupplierAttach::class, 'supplier_id', 'id');
    }

    public function status()
    {
        return $this->hasOne(SuppliersStatus::class, 'id_Supplier');
    }

    public function supplier_links()
    {
        return $this->hasOne(SupplierLink::class, 'id_supplier');
    }

    private function normalizePhoneNumbers($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [$trimmed];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $phones = [];

        foreach ($value as $phone) {
            $phone = trim((string) $phone);

            if ($phone === '' || $phone === '-') {
                continue;
            }

            $phones[] = Str::of($phone)->squish()->value();
        }

        return array_values(array_unique($phones));
    }
}

