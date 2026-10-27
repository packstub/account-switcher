<?php

namespace Packstub\AccountSwitcher\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Packstub\AccountSwitcher\Enums\SwitchReason;

/**
 * Everything the switcher needs to know about the place a switch happens in:
 * which guard holds the session, where the browser goes next, which features
 * are on and what extra rules apply.
 *
 * The package binds AppContext (config and closures) for a plain Laravel app;
 * packstub/filament-account-switcher binds a context that answers from the
 * current panel's plugin. A context of your own is best built on AppContext,
 * so a method added in a minor comes with its default.
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
     * Where to send the browser after signing in as the given user. The
     * reason lets one rule send an impersonation and a developer login to
     * different places; it is null when the caller does not know it.
     */
    public function redirectUrlFor(Authenticatable $user, ?SwitchReason $reason = null): string;

    /**
     * Where to send the browser when the page it lands on after a switch
     * answers 403 or 404, or null to leave that page alone.
     */
    public function fallbackUrlFor(Authenticatable $user): ?string;

    /**
     * Where to send the browser when an impersonation ends and the impersonator
     * no longer exists.
     */
    public function loginUrl(): string;

    public function hasImpersonation(): bool;

    public function hasLinkedAccounts(): bool;

    public function hasDeveloperLogins(): bool;

    /**
     * A runtime gate on the developer logins, on top of the environment list
     * and the feature toggle: checked before the buttons render and again when
     * a login is posted.
     */
    public function allowsDeveloperLogins(): bool;

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

    /**
     * How a developer login is labelled on its button.
     */
    public function developerLoginLabel(Authenticatable $user): string;

    /**
     * A tag for a developer login (its role, its team, ...) that the buttons
     * are grouped under, or null for no tag.
     */
    public function developerLoginGroup(Authenticatable $user): ?string;

    /**
     * Whether a search may find accounts beyond the listed developer logins.
     */
    public function searchesDeveloperLogins(): bool;

    /**
     * The accounts a search finds, when searchesDeveloperLogins() is true.
     *
     * @return Collection<int, Authenticatable>
     */
    public function searchDeveloperLogins(string $search): Collection;

    /**
     * Where a developer login lands when it should differ from redirectUrlFor(),
     * or null for the usual switch redirect.
     */
    public function developerLoginRedirectUrlFor(Authenticatable $user): ?string;

    public function logsSwitches(): bool;

    /**
     * Extra data stored with every switch-log row (the current tenant, ...).
     *
     * @return array<string, mixed>
     */
    public function switchLogContext(SwitchReason $reason): array;

    /**
     * The URL the "Switch back" button posts to, or null when the route is not registered.
     */
    public function switchBackUrl(): ?string;

    /**
     * The URL the developer login buttons post to, or null when the route is not registered.
     */
    public function developerLoginUrl(): ?string;
}
