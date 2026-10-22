<?php

namespace Packstub\AccountSwitcher;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Packstub\AccountSwitcher\Contracts\SwitchContext;

/**
 * The context of a plain Laravel app: the config file for what fits in config,
 * closures registered here (from a service provider's boot method) for the rest.
 *
 *     app(AppContext::class)
 *         ->canImpersonateUsing(fn (User $by, User $target): bool => $by->is_admin)
 *         ->redirectTo(fn (User $user): string => route('dashboard'));
 */
class AppContext implements SwitchContext
{
    protected ?Closure $canAccessUsing = null;

    protected ?Closure $canImpersonateUsing = null;

    protected ?Closure $canSwitchUsing = null;

    protected string|Closure|null $redirectTo = null;

    protected ?Closure $userLabelUsing = null;

    /** @var Closure | array<int, string> | null */
    protected Closure|array|null $developerLoginUsers = null;

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    /**
     * Whether a user may sign in here; a linked account that may not is never
     * offered. Defaults to yes: fn (Authenticatable $user): bool
     */
    public function canAccessUsing(?Closure $callback): static
    {
        $this->canAccessUsing = $callback;

        return $this;
    }

    /**
     * An extra impersonation rule, evaluated after canImpersonate() /
     * canBeImpersonated() on the models: fn (Authenticatable $by, Authenticatable $target): bool
     */
    public function canImpersonateUsing(?Closure $callback): static
    {
        $this->canImpersonateUsing = $callback;

        return $this;
    }

    /**
     * An extra rule for linked-account switching: fn (Authenticatable $from, Authenticatable $target): bool
     */
    public function canSwitchUsing(?Closure $callback): static
    {
        $this->canSwitchUsing = $callback;

        return $this;
    }

    /**
     * Where to land after a switch: a URL, or fn (Authenticatable $user): string.
     * Overrides the redirect_to config key.
     */
    public function redirectTo(string|Closure|null $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    /**
     * How users are named in the banner and the developer login buttons:
     * fn (Authenticatable $user): string. Defaults to the name attribute, then the e-mail.
     */
    public function userLabelUsing(?Closure $callback): static
    {
        $this->userLabelUsing = $callback;

        return $this;
    }

    /**
     * The developer login accounts: a closure returning the users, an array
     * of e-mails, or null for the first ten users. Overrides the
     * developer_logins.users config key; the feature still has to be on.
     *
     * @param  Closure | array<int, string> | null  $users
     */
    public function developerLoginUsersUsing(Closure|array|null $users): static
    {
        $this->developerLoginUsers = $users;

        return $this;
    }

    // ------------------------------------------------------------------
    // SwitchContext
    // ------------------------------------------------------------------

    public function guard(): StatefulGuard
    {
        return auth()->guard($this->guardName());
    }

    public function guardName(): string
    {
        return config('packstub-account-switcher.guard') ?? auth()->getDefaultDriver();
    }

    public function scope(): ?string
    {
        return null;
    }

    public function userLabel(Authenticatable $user): string
    {
        if ($this->userLabelUsing) {
            return (string) ($this->userLabelUsing)($user);
        }

        return (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier());
    }

    public function canAccess(Authenticatable $user): bool
    {
        return $this->canAccessUsing === null || (bool) ($this->canAccessUsing)($user);
    }

    public function redirectUrlFor(Authenticatable $user): string
    {
        $redirect = $this->redirectTo ?? config('packstub-account-switcher.redirect_to');

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user);
        }

        return filled($redirect) ? url($redirect) : url('/');
    }

    public function loginUrl(): string
    {
        $url = config('packstub-account-switcher.login_url');

        if (filled($url)) {
            return url($url);
        }

        return Route::has('login') ? route('login') : url('/');
    }

    public function hasImpersonation(): bool
    {
        return (bool) config('packstub-account-switcher.features.impersonation', true);
    }

    public function hasLinkedAccounts(): bool
    {
        return (bool) config('packstub-account-switcher.features.linked_accounts', true);
    }

    public function hasDeveloperLogins(): bool
    {
        return (bool) config('packstub-account-switcher.features.developer_logins', false);
    }

    public function allowsImpersonation(Authenticatable $by, Authenticatable $target): bool
    {
        return $this->canImpersonateUsing === null || (bool) ($this->canImpersonateUsing)($by, $target);
    }

    public function allowsSwitch(Authenticatable $from, Authenticatable $target): bool
    {
        return $this->canSwitchUsing === null || (bool) ($this->canSwitchUsing)($from, $target);
    }

    public function developerLoginUsers(): Collection
    {
        return AccountSwitcher::resolveUsers(
            $this->developerLoginUsers ?? config('packstub-account-switcher.developer_logins.users'),
        );
    }

    public function logsSwitches(): bool
    {
        return (bool) config('packstub-account-switcher.log_switches', true);
    }

    public function switchBackUrl(): ?string
    {
        return Route::has('account-switcher.switch-back') ? route('account-switcher.switch-back') : null;
    }

    public function developerLoginUrl(): ?string
    {
        return Route::has('account-switcher.developer-login') ? route('account-switcher.developer-login') : null;
    }
}
