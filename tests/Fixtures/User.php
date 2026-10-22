<?php

namespace Packstub\AccountSwitcher\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;

class User extends Authenticatable
{
    use HasLinkedAccounts;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_admin' => false,
        'is_locked' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function canImpersonate(self $target): bool
    {
        return $this->is_admin;
    }

    public function canBeImpersonated(self $by): bool
    {
        return ! $this->is_admin;
    }
}
