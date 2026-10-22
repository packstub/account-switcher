<?php

namespace Packstub\AccountSwitcher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void routes(string $prefix = 'account-switcher', array|string $middleware = 'auth')
 * @method static \Packstub\AccountSwitcher\Contracts\SwitchContext context()
 * @method static bool canImpersonate(\Illuminate\Contracts\Auth\Authenticatable $by, \Illuminate\Contracts\Auth\Authenticatable $target)
 * @method static void impersonate(\Illuminate\Contracts\Auth\Authenticatable $target, ?\Illuminate\Contracts\Auth\Authenticatable $by = null)
 * @method static bool isImpersonating()
 * @method static ?\Illuminate\Contracts\Auth\Authenticatable impersonator()
 * @method static string stopImpersonating()
 * @method static bool canSwitchToLinkedAccount(\Illuminate\Contracts\Auth\Authenticatable $from, \Illuminate\Contracts\Auth\Authenticatable $target)
 * @method static bool requiresPassword(\Illuminate\Contracts\Auth\Authenticatable $from, \Illuminate\Contracts\Auth\Authenticatable $target)
 * @method static void switchToLinkedAccount(\Illuminate\Contracts\Auth\Authenticatable $target, ?string $password = null, ?\Illuminate\Contracts\Auth\Authenticatable $from = null)
 * @method static \Illuminate\Support\Collection switchableAccounts(?\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static bool supportsLinkedAccounts(?\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static bool passwordMatches(\Illuminate\Contracts\Auth\Authenticatable $account, ?string $password)
 * @method static bool developerLoginsEnabled()
 * @method static \Illuminate\Support\Collection developerLoginUsers()
 * @method static void developerLogin(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static string redirectUrlFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static class-string<\Illuminate\Database\Eloquent\Model> userModel()
 * @method static ?string connectionName()
 * @method static \Illuminate\Support\Collection resolveUsers(\Closure|array|null $users)
 * @method static void macro(string $name, object|callable $macro)
 *
 * @see \Packstub\AccountSwitcher\AccountSwitcher
 */
class AccountSwitcher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Packstub\AccountSwitcher\AccountSwitcher::class;
    }
}
