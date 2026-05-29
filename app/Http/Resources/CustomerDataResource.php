<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama_perusahaan,
            'email' => $this->email,
            'phone' => $this->formatted_no_telp ?: '',
            'perusahaan_id' => $this->id_perusahaan,
            'created_at' => $this->created_at,
        ];
    }
}
