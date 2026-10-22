<?php

namespace Packstub\AccountSwitcher\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;

/**
 * Everything the switcher needs to know about the place a switch happens in:
 * which guard holds the session, where the browser goes next, which features
 * are on and what extra rules apply.
 *
 * The package binds AppContext (config and closures) for a plain Laravel app;
 * packstub/filament-account-switcher binds a context that answers from the
 * current panel's plugin.
 */
interface SwitchContext
{
    public function guard(): StatefulGuard;

    public function guardName(): string;

    /**
     * A short name for where the switch happened, stored in the switch log
     * (a Filament panel id, or null when the app has one place to sign in).
     */
    public function scope(): ?string;

    /**
     * How a user is named in the banner and the developer login buttons.
     */
    public function userLabel(Authenticatable $user): string;

    /**
     * Whether the user may sign in here at all. A linked account that may
     * not cannot be switched to.
     */
    public function canAccess(Authenticatable $user): bool;

    /**
     * Where to send the browser after signing in as the given user.
     */
    public function redirectUrlFor(Authenticatable $user): string;

    /**
     * Where to send the browser when an impersonation ends and the impersonator
     * no longer exists.
     */
    public function loginUrl(): string;

    public function hasImpersonation(): bool;

    public function hasLinkedAccounts(): bool;

    public function hasDeveloperLogins(): bool;

    /**
     * An extra rule on top of canImpersonate() / canBeImpersonated() on the models.
     */
    public function allowsImpersonation(Authenticatable $by, Authenticatable $target): bool;

    /**
     * An extra rule on top of the link itself.
     */
    public function allowsSwitch(Authenticatable $from, Authenticatable $target): bool;

    /**
     * The accounts offered as one-click developer logins. The environment
     * ceiling in the config is applied by the switcher, not here.
     *
     * @return Collection<int, Authenticatable>
     */
    public function developerLoginUsers(): Collection;

    public function logsSwitches(): bool;

    /**
     * The URL the "Switch back" button posts to, or null when the route is not registered.
     */
    public function switchBackUrl(): ?string;

    /**
     * The URL the developer login buttons post to, or null when the route is not registered.
     */
    public function developerLoginUrl(): ?string;
}
