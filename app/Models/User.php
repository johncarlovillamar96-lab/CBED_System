<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public const ADMIN = 'admin';
    public const TECHNICIAN = 'technician';
    public const NON_ADMIN = 'non-admin';
    public const MIN_PASSWORD_LENGTH = 8;

    public const ROLES = [
        self::ADMIN => 'Admin',
        self::TECHNICIAN => 'Technician',
        self::NON_ADMIN => 'Non-admin',
    ];

    protected $fillable = ['name', 'first_name', 'last_name', 'email', 'password', 'role'];

    protected $attributes = ['role' => self::NON_ADMIN];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    public function canManageMonitoring(): bool
    {
        return $this->isAdmin() || $this->role === self::TECHNICIAN;
    }
}
