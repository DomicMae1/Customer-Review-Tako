<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Supplier;

class SupplierLink extends Model
{
    protected $connection = 'tako-perusahaan'; 
    protected $table = 'supplier_links';
    protected $primaryKey = 'id_link'; 

    protected $fillable = [
        'id_user',
        'id_perusahaan',
        'id_supplier',     
        'token',            
        'url',       
        'nama_supplier',    
        'is_filled',       
        'filled_at', 
    ];

    protected $casts = [
        'is_filled' => 'boolean',
        'filled_at' => 'datetime',
    ];

    /**
     * Relasi ke user pembuat link
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'id_perusahaan');
    }

    /**
     * Relasi ke supplier (jika sudah terhubung)
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier');
    }
}
