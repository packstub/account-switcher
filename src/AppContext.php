<?php

namespace Packstub\AccountSwitcher;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;

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

    protected string|Closure|null $fallbackTo = null;

    protected ?Closure $userLabelUsing = null;

    /** @var Closure | array<int, string> | null */
    protected Closure|array|null $developerLoginUsers = null;

    protected ?Closure $developerLoginsWhen = null;

    protected ?Closure $developerLoginLabelUsing = null;

    protected ?Closure $developerLoginGroupUsing = null;

    protected ?Closure $developerLoginSearchUsing = null;

    protected string|Closure|null $developerLoginRedirectTo = null;

    protected ?Closure $switchLogContextUsing = null;

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
     * Where to land after a switch: a URL, or fn (Authenticatable $user, ?SwitchReason $reason): string.
     * Overrides the redirect_to config key.
     */
    public function redirectTo(string|Closure|null $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    /**
     * Where to send the browser when the page it lands on after a switch
     * answers 403 or 404: a URL, or fn (Authenticatable $user): string. Off
     * until set; the page is then shown as usual.
     */
    public function fallbackTo(string|Closure|null $url): static
    {
        $this->fallbackTo = $url;

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

    /**
     * A runtime gate on the developer logins, on top of the environment list,
     * checked before the buttons render and again when a login is posted: fn (): bool
     */
    public function developerLoginsWhen(?Closure $callback): static
    {
        $this->developerLoginsWhen = $callback;

        return $this;
    }

    /**
     * How a developer login is labelled on its button: fn (Authenticatable $user): string.
     * Defaults to userLabel().
     */
    public function developerLoginLabelUsing(?Closure $callback): static
    {
        $this->developerLoginLabelUsing = $callback;

        return $this;
    }

    /**
     * A tag for each developer login (its role, its team, ...) the buttons are
     * grouped under: fn (Authenticatable $user): ?string
     */
    public function developerLoginGroupUsing(?Closure $callback): static
    {
        $this->developerLoginGroupUsing = $callback;

        return $this;
    }

    /**
     * Let a search find any account, not only the listed developer logins:
     * fn (string $search): iterable<Authenticatable>. Keep the result small (limit it).
     */
    public function developerLoginSearchUsing(?Closure $callback): static
    {
        $this->developerLoginSearchUsing = $callback;

        return $this;
    }

    /**
     * Where a developer login lands, when it should differ from redirectTo():
     * a URL, or fn (Authenticatable $user): string
     */
    public function developerLoginRedirectTo(string|Closure|null $url): static
    {
        $this->developerLoginRedirectTo = $url;

        return $this;
    }

    /**
     * Extra data stored with every switch-log row, e.g. the current tenant:
     * fn (SwitchReason $reason): array
     */
    public function switchLogContextUsing(?Closure $callback): static
    {
        $this->switchLogContextUsing = $callback;

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

    public function redirectUrlFor(Authenticatable $user, ?SwitchReason $reason = null): string
    {
        $redirect = $this->redirectTo ?? config('packstub-account-switcher.redirect_to');

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user, $reason);
        }

        return filled($redirect) ? url($redirect) : url('/');
    }

    public function fallbackUrlFor(Authenticatable $user): ?string
    {
        $url = $this->fallbackTo;

        if ($url instanceof Closure) {
            $url = $url($user);
        }

        return filled($url) ? url($url) : null;
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

    public function allowsDeveloperLogins(): bool
    {
        return $this->developerLoginsWhen === null || (bool) ($this->developerLoginsWhen)();
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

    public function developerLoginLabel(Authenticatable $user): string
    {
        if ($this->developerLoginLabelUsing) {
            return (string) ($this->developerLoginLabelUsing)($user);
        }

        return $this->userLabel($user);
    }

    public function developerLoginGroup(Authenticatable $user): ?string
    {
        if (! $this->developerLoginGroupUsing) {
            return null;
        }

        $group = ($this->developerLoginGroupUsing)($user);

        return filled($group) ? (string) $group : null;
    }

    public function searchesDeveloperLogins(): bool
    {
        return $this->developerLoginSearchUsing !== null;
    }

    public function searchDeveloperLogins(string $search): Collection
    {
        if (! $this->developerLoginSearchUsing) {
            return collect();
        }

        return collect(($this->developerLoginSearchUsing)($search))->values();
    }

    public function developerLoginRedirectUrlFor(Authenticatable $user): ?string
    {
        $redirect = $this->developerLoginRedirectTo;

        if ($redirect instanceof Closure) {
            $redirect = $redirect($user);
        }

        return filled($redirect) ? url($redirect) : null;
    }

    public function logsSwitches(): bool
    {
        return (bool) config('packstub-account-switcher.log_switches', true);
    }

    public function switchLogContext(SwitchReason $reason): array
    {
        return $this->switchLogContextUsing ? (array) ($this->switchLogContextUsing)($reason) : [];
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
