<?php

namespace Packstub\AccountSwitcher;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Events\AccountSwitched;
use Packstub\AccountSwitcher\Events\AccountSwitching;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;
use Packstub\AccountSwitcher\Http\Controllers\DeveloperLoginController;
use Packstub\AccountSwitcher\Http\Controllers\ImpersonateController;
use Packstub\AccountSwitcher\Http\Controllers\SwitchAccountController;
use Packstub\AccountSwitcher\Http\Controllers\SwitchBackController;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The session core shared by all three features. Every switch goes through
 * authenticateAs(): re-login on the context's guard, session regeneration, and
 * clearing Laravel's per-guard password hash so AuthenticateSession does not
 * log the new user out on the next request.
 *
 * What differs between a plain app and a panel (guard, redirects, feature
 * toggles, extra rules) is asked from the bound SwitchContext.
 */
class AccountSwitcher
{
    use Macroable;

    public const SESSION_KEY = 'packstub_account_switcher';

    /**
     * Flashed on every switch: where to send the browser when the page it lands on is hidden from the new account.
     */
    public const FALLBACK_URL_SESSION_KEY = 'packstub_account_switcher_fallback_url';

    protected static ?Closure $logContextResolver = null;

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('packstub-account-switcher.user_model')
            ?? config('auth.providers.users.model');
    }

    /**
     * The connection the package's tables live on: the configured one, or
     * the user model's own connection (null = the application default).
     */
    public static function connectionName(): ?string
    {
        $connection = config('packstub-account-switcher.connection');

        if (filled($connection)) {
            return $connection;
        }

        $userModel = static::userModel();

        return $userModel ? (new $userModel)->getConnectionName() : null;
    }

    /**
     * Users from a closure returning them, an array of e-mails, or null for
     * the first ten users by key. Shared by every place a user list is configured.
     *
     * @param  Closure | array<int, string> | null  $users
     * @return Collection<int, Authenticatable>
     */
    public static function resolveUsers(Closure|array|null $users): Collection
    {
        $model = static::userModel();

        if ($users instanceof Closure) {
            return collect($users());
        }

        if (is_array($users)) {
            return $model::query()->whereIn('email', $users)->orderBy((new $model)->getKeyName())->get();
        }

        return $model::query()->orderBy((new $model)->getKeyName())->limit(10)->get();
    }

    /**
     * Register the package's routes inside the current route group:
     *
     *     POST {prefix}/impersonate/{user}  account-switcher.impersonate     (authenticated)
     *     POST {prefix}/switch-back         account-switcher.switch-back     (authenticated)
     *     POST {prefix}/switch              account-switcher.switch          (authenticated)
     *     POST {prefix}/developer-login     account-switcher.developer-login (guest, throttled)
     *
     * Call it from routes/web.php so the web middleware (session, CSRF) applies.
     *
     * @param  array<int, string> | string  $middleware  The middleware of the authenticated routes.
     */
    public function routes(string $prefix = 'account-switcher', array|string $middleware = 'auth'): void
    {
        Route::prefix($prefix)->name('account-switcher.')->group(function () use ($middleware): void {
            Route::middleware($middleware)->group(function (): void {
                Route::post('impersonate/{user}', ImpersonateController::class)->name('impersonate');
                Route::post('switch-back', SwitchBackController::class)->name('switch-back');
                Route::post('switch', SwitchAccountController::class)->name('switch');
            });

            Route::post('developer-login', DeveloperLoginController::class)
                ->middleware('throttle:10,1')
                ->name('developer-login');
        });
    }

    public function context(): SwitchContext
    {
        return app(SwitchContext::class);
    }

    // ------------------------------------------------------------------
    // Impersonation
    // ------------------------------------------------------------------

    public function canImpersonate(Authenticatable $by, Authenticatable $target): bool
    {
        if ($by->getAuthIdentifier() === $target->getAuthIdentifier()) {
            return false;
        }

        if ($this->isImpersonating()) {
            return false;
        }

        $context = $this->context();

        if (! $context->hasImpersonation()) {
            return false;
        }

        if (method_exists($by, 'canImpersonate') && ! $by->canImpersonate($target)) {
            return false;
        }

        if (method_exists($target, 'canBeImpersonated') && ! $target->canBeImpersonated($by)) {
            return false;
        }

        return $context->allowsImpersonation($by, $target);
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function impersonate(Authenticatable $target, ?Authenticatable $by = null): void
    {
        $by ??= $this->guard()->user();

        if (! $by || ! $this->canImpersonate($by, $target)) {
            throw AccountSwitchDenied::cannotImpersonate();
        }

        AccountSwitching::dispatch($by, $target, SwitchReason::Impersonation);

        session()->put(self::SESSION_KEY.'.impersonator_id', $by->getAuthIdentifier());
        session()->put(self::SESSION_KEY.'.impersonator_guard', $this->guardName());
        session()->put(self::SESSION_KEY.'.back_to', url()->previous());

        $this->authenticateAs($target);
        $this->record($by, $target, SwitchReason::Impersonation);

        AccountSwitched::dispatch($by, $target, SwitchReason::Impersonation);
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY.'.impersonator_id');
    }

    public function impersonator(): ?Authenticatable
    {
        if (! $this->isImpersonating()) {
            return null;
        }

        $guard = session()->get(self::SESSION_KEY.'.impersonator_guard', $this->guardName());

        return auth()->guard($guard)->getProvider()->retrieveById(
            session()->get(self::SESSION_KEY.'.impersonator_id'),
        );
    }

    /**
     * Restore the impersonator's session and return the URL to send them to.
     */
    public function stopImpersonating(): string
    {
        $impersonator = $this->impersonator();
        $impersonated = $this->guard()->user();
        $backTo = session()->get(self::SESSION_KEY.'.back_to');

        session()->forget(self::SESSION_KEY);

        if (! $impersonator) {
            $this->guard()->logout();

            return $this->context()->loginUrl();
        }

        $this->authenticateAs($impersonator);

        if ($impersonated) {
            $this->record($impersonated, $impersonator, SwitchReason::ImpersonationEnded);
            AccountSwitched::dispatch($impersonated, $impersonator, SwitchReason::ImpersonationEnded);
        }

        return $backTo ?: $this->redirectUrlFor($impersonator, SwitchReason::ImpersonationEnded);
    }

    // ------------------------------------------------------------------
    // Linked accounts
    // ------------------------------------------------------------------

    public function canSwitchToLinkedAccount(Authenticatable $from, Authenticatable $target): bool
    {
        if ($this->isImpersonating()) {
            return false;
        }

        if ($from->getAuthIdentifier() === $target->getAuthIdentifier()) {
            return false;
        }

        $context = $this->context();

        // The feature toggle comes before isLinkedTo(): with linked accounts
        // off the table may not exist, and nothing here should query it.
        if (! $context->hasLinkedAccounts() || ! $this->supportsLinkedAccounts($from)) {
            return false;
        }

        if (! $from->isLinkedTo($target) || ! $context->canAccess($target)) {
            return false;
        }

        return $context->allowsSwitch($from, $target);
    }

    public function requiresPassword(Authenticatable $from, Authenticatable $target): bool
    {
        if (! $this->supportsLinkedAccounts($from)) {
            return true;
        }

        $link = $from->linkedAccounts()->whereKey($target->getAuthIdentifier())->first();

        return $link?->pivot->requires_password ?? true;
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function switchToLinkedAccount(Authenticatable $target, ?string $password = null, ?Authenticatable $from = null): void
    {
        $from ??= $this->guard()->user();

        if ($this->isImpersonating()) {
            throw AccountSwitchDenied::whileImpersonating();
        }

        if (! $from || ! $this->canSwitchToLinkedAccount($from, $target)) {
            throw AccountSwitchDenied::notLinked();
        }

        if ($this->requiresPassword($from, $target) && ! $this->passwordMatches($target, $password)) {
            throw AccountSwitchDenied::invalidPassword();
        }

        AccountSwitching::dispatch($from, $target, SwitchReason::LinkedAccount);

        $this->authenticateAs($target);
        $this->record($from, $target, SwitchReason::LinkedAccount);

        AccountSwitched::dispatch($from, $target, SwitchReason::LinkedAccount);
    }

    /**
     * The linked accounts the user may switch to right now.
     *
     * @return Collection<int, Model&Authenticatable>
     */
    public function switchableAccounts(?Authenticatable $user): Collection
    {
        if (! $this->supportsLinkedAccounts($user) || $this->isImpersonating() || ! $this->context()->hasLinkedAccounts()) {
            return new Collection;
        }

        return $user->linkedAccounts
            ->filter(fn (Model $account): bool => $this->canSwitchToLinkedAccount($user, $account))
            ->values();
    }

    public function supportsLinkedAccounts(?Authenticatable $user): bool
    {
        return $user !== null && in_array(HasLinkedAccounts::class, class_uses_recursive($user), true);
    }

    /**
     * Verify that the given plain-text password belongs to the account.
     */
    public function passwordMatches(Authenticatable $account, ?string $password): bool
    {
        $hash = $account->getAuthPassword();

        return filled($password) && filled($hash) && Hash::check($password, $hash);
    }

    // ------------------------------------------------------------------
    // Developer logins
    // ------------------------------------------------------------------

    public function developerLoginsEnabled(): bool
    {
        $environments = config('packstub-account-switcher.developer_logins.environments', ['local']);
        $context = $this->context();

        return app()->environment($environments)
            && $context->hasDeveloperLogins()
            && $context->allowsDeveloperLogins();
    }

    /**
     * @return Collection<int, Authenticatable>
     */
    public function developerLoginUsers(): Collection
    {
        if (! $this->developerLoginsEnabled()) {
            return collect();
        }

        return $this->context()->developerLoginUsers();
    }

    /**
     * The accounts matching a search: the context's search when it has one, else the listed
     * accounts filtered by the text (label, tag, name, e-mail, username). Empty outside the gates.
     *
     * @return Collection<int, Authenticatable>
     */
    public function searchDeveloperLogins(string $search): Collection
    {
        if (! $this->developerLoginsEnabled()) {
            return collect();
        }

        $context = $this->context();

        if ($context->searchesDeveloperLogins()) {
            return $context->searchDeveloperLogins($search)->values();
        }

        $search = mb_strtolower(trim($search));

        return $this->developerLoginUsers()
            ->filter(fn (Authenticatable $user): bool => $search === '' || str_contains(mb_strtolower(implode(' ', array_filter([
                $context->developerLoginLabel($user),
                $context->developerLoginGroup($user),
                $user->name ?? null,
                $user->email ?? null,
                $user->username ?? null,
            ]))), $search))
            ->values();
    }

    /**
     * Whether the account may be signed into through the developer logins: listed, or found by the search.
     */
    public function isDeveloperLoginAllowed(Authenticatable $user, ?string $search = null): bool
    {
        $matches = fn (Authenticatable $candidate): bool => (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier();

        if ($this->developerLoginUsers()->contains($matches)) {
            return true;
        }

        return filled($search) && $this->searchDeveloperLogins($search)->contains($matches);
    }

    /**
     * @throws AccountSwitchDenied
     */
    public function developerLogin(Authenticatable $user, ?string $search = null): void
    {
        if (! $this->developerLoginsEnabled()) {
            throw AccountSwitchDenied::developerLoginsDisabled();
        }

        if (! $this->isDeveloperLoginAllowed($user, $search)) {
            throw AccountSwitchDenied::developerLoginsDisabled();
        }

        $from = $this->guard()->user();

        AccountSwitching::dispatch($from, $user, SwitchReason::DeveloperLogin);

        session()->forget(self::SESSION_KEY);
        $this->authenticateAs($user);
        $this->record($from, $user, SwitchReason::DeveloperLogin);

        AccountSwitched::dispatch($from, $user, SwitchReason::DeveloperLogin);
    }

    /**
     * Where a developer login lands: the context's own answer if it has one, else the usual switch redirect.
     */
    public function developerLoginRedirectUrl(Authenticatable $user): string
    {
        return $this->context()->developerLoginRedirectUrlFor($user)
            ?? $this->redirectUrlFor($user, SwitchReason::DeveloperLogin);
    }

    // ------------------------------------------------------------------
    // Redirects
    // ------------------------------------------------------------------

    /**
     * Where to land after switching to the given user.
     */
    public function redirectUrlFor(Authenticatable $user, ?SwitchReason $reason = null): string
    {
        return $this->context()->redirectUrlFor($user, $reason);
    }

    /**
     * After a switch, a 403 or 404 on the page the browser landed on is answered with a redirect to
     * the context's fallback URL. It is flashed by the switch, so it applies to that one request only.
     */
    public function redirectWhenPageIsInaccessible(HttpExceptionInterface $exception, Request $request): ?RedirectResponse
    {
        if (! in_array($exception->getStatusCode(), [403, 404], true) || ! $request->isMethod('GET') || ! $request->hasSession()) {
            return null;
        }

        $fallbackUrl = $request->session()->pull(self::FALLBACK_URL_SESSION_KEY);

        if (! is_string($fallbackUrl) || $fallbackUrl === $request->url()) {
            return null;
        }

        // Not redirect(): while Livewire mounts a page its own redirector is bound in place of Laravel's.
        return new RedirectResponse($fallbackUrl);
    }

    // ------------------------------------------------------------------
    // Switch log
    // ------------------------------------------------------------------

    /**
     * Extra data for every switch-log row, app-wide (e.g. the current tenant): fn (SwitchReason $reason): array
     */
    public static function resolveLogContextUsing(?Closure $callback): void
    {
        static::$logContextResolver = $callback;
    }

    /**
     * Write a switch-log row for a switch that happened elsewhere (an SSO hand-off, a tenancy
     * impersonation token, ...). Honours the same on/off switch as the package's own rows.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(?Authenticatable $from, Authenticatable $to, SwitchReason $reason, array $context = []): ?AccountSwitch
    {
        if (! $this->context()->logsSwitches()) {
            return null;
        }

        $context = [
            ...(static::$logContextResolver ? (array) (static::$logContextResolver)($reason) : []),
            ...$this->context()->switchLogContext($reason),
            ...$context,
        ];

        return AccountSwitch::query()->create([
            'from_user_id' => $from?->getAuthIdentifier(),
            'to_user_id' => $to->getAuthIdentifier(),
            'reason' => $reason,
            'panel' => $this->context()->scope(),
            'guard' => $this->guardName(),
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function guard(): StatefulGuard
    {
        return $this->context()->guard();
    }

    protected function guardName(): string
    {
        return $this->context()->guardName();
    }

    protected function authenticateAs(Authenticatable $user): void
    {
        $this->guard()->login($user);

        session()->regenerate();

        session()->forget(array_unique([
            'password_hash_'.$this->guardName(),
            'password_hash_'.auth()->getDefaultDriver(),
            'password_hash_'.config('auth.defaults.guard'),
        ]));

        $fallbackUrl = $this->context()->fallbackUrlFor($user);

        if ($fallbackUrl !== null) {
            session()->flash(self::FALLBACK_URL_SESSION_KEY, $fallbackUrl);
        }
    }

    protected function record(?Authenticatable $from, Authenticatable $to, SwitchReason $reason): void
    {
        $this->log($from, $to, $reason);
    }
}
