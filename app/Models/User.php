<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use Uspdev\SenhaunicaSocialite\Traits\HasSenhaunica;
use App\Models\Setor;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory,Notifiable,HasRoles,HasSenhaunica;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'codpes',
        'foto',
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

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    public function fechaduras()
    {
        return $this->belongsToMany(Fechadura::class);
    }

    public function temFotoLocal()
    {
        return !empty($this->foto) && Storage::disk('fotos')->exists($this->foto);
    }
}
