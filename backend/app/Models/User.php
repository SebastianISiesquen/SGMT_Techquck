<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'telefono',
        'cargo',
        'activo',
        'ultimo_acceso',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'activo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function incidenciasAsignadas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'tecnico_id');
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole('Administrador');
    }

    public function rolNombre(): string
    {
        return $this->getRoleNames()->first() ?? 'Sin rol';
    }

    public function iniciales(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /** Usuarios que pueden recibir incidencias (permiso de atención técnica por rol o directo). */
    public function scopeTecnicos($query)
    {
        return $query->activos()->permission('atenciones.registrar')->orderBy('name');
    }
}
