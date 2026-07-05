<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierAttach extends Model
{
    use HasFactory, SoftDeletes;
    protected $connection = 'tako-customer';
    protected $table = 'supplier_attaches';

    protected $fillable = [
        'supplier_id',
        'nama_file',
        'path',
        'type',
    ];

    /**
     * Relasi ke model Supplier.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
