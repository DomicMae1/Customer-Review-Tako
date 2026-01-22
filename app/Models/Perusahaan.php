<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Domain;

class Perusahaan extends Model
{
    use HasFactory;

    protected $connection = 'tako-perusahaan';
    protected $table = 'perusahaan';

    protected $fillable = [
        'nama_perusahaan',
        'id_domain',
        'notify_1',
        'notify_2',
        'data',
        'id_User_1',
        'id_User_2',
        'id_User_3',
        'id_User',
    ];

    protected $casts = [
        'data' => 'json',
    ];

    public function user1()
    {
        return $this->belongsTo(User::class, 'id_User_1');
    }
    public function user2()
    {
        return $this->belongsTo(User::class, 'id_User_2');
    }
    public function user3()
    {
        return $this->belongsTo(User::class, 'id_User_3');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'id_User');
    }

    public function hasManager(): bool
    {
        // Cek via relasi pivot
        $pivotManager = $this->users()->wherePivot('role', 'manager')->exists();

        // Cek via kolom id_User_1
        $directManager = !is_null($this->id_User_1);

        return $pivotManager || $directManager;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'perusahaan_user_roles', 'id_perusahaan', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tenant()
    {
        return $this->hasOne(Tenant::class, 'perusahaan_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'id_domain');
    }
}
