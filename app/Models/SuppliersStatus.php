<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuppliersStatus extends Model
{
    protected $connection = 'tako-perusahaan';

    protected $table = 'suppliers_statuses'; 

    protected $primaryKey = 'id_Supplier';     

    public $incrementing = true;              

    protected $keyType = 'int';             

    protected $fillable = [
        'id_user',
        'submit_1_timestamps',
        'submit_1_nama_file',
        'submit_1_path',

        'status_1_by',
        'status_1_timestamps',
        'status_1_keterangan',
        'status_1_nama_file',
        'status_1_path',

        'status_2_by',
        'status_2_timestamps',
        'status_2_keterangan',
        'status_2_nama_file',
        'status_2_path',

        'status_3_by',
        'status_3_timestamps',
        'status_3_keterangan',
        'status_3',
        'submit_3_nama_file',
        'submit_3_path',

        'status_4_by',
        'status_4_timestamps',
        'status_4_keterangan',
        'status_4_nama_file',
        'status_4_path',
    ];

    protected $dates = [
        'status_1_timestamps',
        'submit_1_timestamps',
        'status_2_timestamps',
        'submit_2_timestamps',
        'status_3_timestamps',
        'submit_3_timestamps',
        'status_4_timestamps',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_Supplier');
    }

    public function submit1By()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function status1Approver()
    {
        return $this->belongsTo(User::class, 'status_1_by');
    }

    public function status2Approver()
    {
        return $this->belongsTo(User::class, 'status_2_by');
    }

    public function status3Approver()
    {
        return $this->belongsTo(User::class, 'status_3_by');
    }

    public function status4Approver()
    {
        return $this->belongsTo(User::class, 'status_4_by');
    }
}
