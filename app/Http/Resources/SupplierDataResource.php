<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'uid_perusahaan' => $this->perusahaan?->uid ?? '',
            'uid_marketing' => $this->creator?->uid ?? '',
            'nama_perusahaan' => $this->nama_perusahaan,
            'type' => $this->jenis_perusahaan ?? '',
            'kategori' => $this->supplier_category ?? $this->kategori_usaha ?? '',
            'kategori_lain' => null,
            'ownership' => null,
            'created_by' => $this->creator?->name ?? '',
            'updated_by' => null,
            'no_npwp' => $this->no_npwp,
            'no_npwp_16' => $this->no_npwp_16,
            'nib' => $this->nib,
            'email' => $this->email,
            'nama' => $this->nama_personal ?? $this->nama_pj ?? '',
            'email_to' => null,
            'email_cc' => null,
            'alamat_lengkap' => $this->alamat_lengkap,
            'kategori_usaha' => $this->kategori_usaha,
            'bentuk_badan_usaha' => $this->bentuk_badan_usaha,
            'kota' => $this->kota,
            'no_telp' => $this->formatted_no_telp ?: '',
            'no_fax' => $this->no_fax,
            'alamat_penagihan' => $this->alamat_penagihan,
            'website' => $this->website,
            'top' => $this->top,
            'status_perpajakan' => $this->status_perpajakan,
            'nama_pj' => $this->nama_pj,
            'no_ktp_pj' => $this->no_ktp_pj,
            'no_telp_pj' => $this->no_telp_pj,
            'nama_personal' => $this->nama_personal,
            'jabatan_personal' => $this->jabatan_personal,
            'no_telp_personal' => $this->formatted_no_telp_personal ?: '',
            'email_personal' => $this->email_personal,
            'bank_accounts' => $this->bank_accounts ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
