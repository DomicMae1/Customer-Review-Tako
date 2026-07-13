<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $connection = 'tako-perusahaan';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'NIK',
        'uid',
        'email',
        'password',
        'id_perusahaan',
        'integration_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function perusahaan()
    {
        return $this->belongsToMany(Perusahaan::class, 'perusahaan_user_roles', 'user_id', 'id_perusahaan')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Perusahaan::class, 'perusahaan_user_roles', 'user_id', 'id_perusahaan')
            ->withPivot('role')
            ->withTimestamps();
    }
}
