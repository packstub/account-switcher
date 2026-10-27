# Configuration

Settings that fit in config live in `config/packstub-account-switcher.php`. Rules that need code go on the app context, from a service provider's `boot()` method.

## The config file

```bash
php artisan vendor:publish --tag="account-switcher-config"
```

```php
return [
    // The model linked accounts and the switch log point to.
    // null = config('auth.providers.users.model').
    'user_model' => null,

    // The session guard a switch signs in on. null = the default guard.
    'guard' => null,

    // The connection both tables live on. null = the user model's connection.
    'connection' => null,

    'tables' => [
        'linked_accounts' => 'linked_accounts',
        'account_switches' => 'account_switches',
    ],

    'features' => [
        'impersonation' => true,
        'linked_accounts' => true,
        'developer_logins' => false,
    ],

    // After a switch. null = the home page.
    'redirect_to' => null,

    // After an impersonation ends and the impersonator is gone.
    // null = the `login` route when there is one, else the home page.
    'login_url' => null,

    // Write an account_switches row for every switch. Off, together with
    // features.linked_accounts, the package runs without its migrations.
    'log_switches' => true,

    'developer_logins' => [
        // Hard ceiling; nothing else can override it.
        'environments' => ['local'],
        // E-mails, or null for the first ten users.
        'users' => null,
    ],

    'banner' => [
        'position' => 'bottom', // 'top' | 'bottom'
        'style' => 'dark',      // 'dark' | 'light'
    ],
];
```

### User model

Linked accounts and the switch log are relationships on one user model. It is resolved from `user_model`, falling back to `auth.providers.users.model`. Set it **before** running the migrations: they derive the foreign keys and the referenced table from it.

### Connection

Both tables follow the user model's connection, so they always sit next to the `users` table. That is what a multi-database tenancy setup needs: a user model on a central connection keeps linked accounts and the switch log central, even while a request runs on a tenant connection. Set `connection` to pin them somewhere else. The migrations honour the same setting.

### Redirects

After a switch the browser goes to `redirectTo()` on the app context if set, else `redirect_to` from the config, else the home page. A closure receives the user who was just signed in and the `SwitchReason`, so one rule can send an impersonation and a developer login to different places. Developer logins may have a landing page of their own, `developerLoginRedirectTo()`; see [Developer logins](developer-logins.md#where-it-lands).

### Landing on a hidden page

The page the browser lands on after a switch may be one the new account cannot see. Give the app context a fallback and a 403 or 404 on that first page is answered with a redirect to it, once; later 403s and 404s are shown as usual:

```php
$this->app->make(AppContext::class)
    ->fallbackTo(fn (User $user): string => route('dashboard'));
```

Off until set. The switch flashes the URL in the session (`AccountSwitcher::FALLBACK_URL_SESSION_KEY`) and the package's exception handler hook reads it on the next request.

## The app context

`Packstub\AccountSwitcher\AppContext` is where code-level rules go. It is a singleton; configure it in a service provider:

```php
use Packstub\AccountSwitcher\AppContext;

public function boot(): void
{
    $this->app->make(AppContext::class)
        // Extra rules, evaluated after canImpersonate() / canBeImpersonated() on the models
        ->canImpersonateUsing(fn (User $by, User $target): bool => $by->can('impersonate', $target))
        ->canSwitchUsing(fn (User $from, User $to): bool => ! $to->is_locked)

        // Whether an account may sign in here at all (a linked account that may not is never offered)
        ->canAccessUsing(fn (User $user): bool => $user->is_active)

        // Where to land after a switch (string or Closure(Authenticatable, ?SwitchReason): string)
        ->redirectTo(fn (User $user, ?SwitchReason $reason): string => route('dashboard'))

        // Where a 403 or 404 landing page is sent, once (off until set)
        ->fallbackTo(fn (User $user): string => route('dashboard'))

        // How users are named in the banner and the developer login buttons
        ->userLabelUsing(fn (User $user): string => $user->full_name)

        // The developer login accounts (a closure returning users, e-mails, or null for the first ten)
        ->developerLoginUsersUsing(fn () => User::query()->where('role', 'developer')->get())

        // Developer logins: a runtime gate, labels, tags, a search beyond the list, their own landing page
        ->developerLoginsWhen(fn (): bool => app()->environment('local') || auth()->check())
        ->developerLoginLabelUsing(fn (User $user): string => "{$user->role} ({$user->username})")
        ->developerLoginGroupUsing(fn (User $user): ?string => $user->role)
        ->developerLoginSearchUsing(fn (string $search) => User::query()->whereLike('email', "%{$search}%", caseSensitive: false)->limit(20)->get())
        ->developerLoginRedirectTo('/app')

        // Extra data for every switch-log row
        ->switchLogContextUsing(fn (SwitchReason $reason): array => ['tenant' => tenant('id')]);
}
```

## The switch log

Every switch writes one row to `account_switches`:

| Column | Meaning |
| --- | --- |
| `from_user_id` | Who was signed in (null for a developer login from the login page) |
| `to_user_id` | Who the session became |
| `reason` | `impersonation`, `impersonation_ended`, `linked_account`, `developer_login` (`SwitchReason` enum) |
| `panel` | Where it happened: the context's scope (null in a plain app, the panel id in Filament) |
| `guard` | The guard the session was signed in on |
| `ip_address`, `user_agent` | From the request |
| `context` | Extra data you attach (JSON, nullable), see below |
| `created_at` | When |

Query it with the `Packstub\AccountSwitcher\Models\AccountSwitch` model (`fromUser()` / `toUser()` relationships) for an "Account activity" page. Turn logging off with `log_switches`.

### Context

Attach what the row cannot infer: the tenant, a ticket number, the client app. App-wide, from a service provider:

```php
use Packstub\AccountSwitcher\AccountSwitcher;

AccountSwitcher::resolveLogContextUsing(fn (SwitchReason $reason): array => [
    'tenant' => tenant()?->getTenantKey(),
]);
```

The context's own data is merged over it (`switchLogContextUsing()` on the app context, `->switchLogContextUsing()` on a Filament panel's plugin), and what you pass to `log()` over both.

Installs that predate the column get it from the guarded `add_context_to_account_switches_table` migration: `php artisan vendor:publish --tag="account-switcher-migrations"`, then `php artisan migrate`.

### Switches made elsewhere

Some switches never go through the package: a tenancy package's impersonation token redeemed on another domain, an SSO hand-off. Log them in the same table so the audit trail stays complete:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::log(from: $centralUser, to: $tenantUser, reason: SwitchReason::Impersonation, context: ['tenant' => $tenant->id]);
```

It honours `log_switches` and returns the row, or `null` when logging is off.

## Events

| Event | When | Payload |
| --- | --- | --- |
| `Packstub\AccountSwitcher\Events\AccountSwitching` | After authorization passed, before the session changes. Throw to abort. | `?Authenticatable $from`, `Authenticatable $to`, `SwitchReason $reason` |
| `Packstub\AccountSwitcher\Events\AccountSwitched` | After the session changed and the log row was written | same |

```php
use Packstub\AccountSwitcher\Events\AccountSwitched;

Event::listen(AccountSwitched::class, function (AccountSwitched $event): void {
    if ($event->reason === SwitchReason::Impersonation && $event->to->is_vip) {
        Notification::route('mail', 'security@example.com')->notify(new VipImpersonated($event));
    }
});
```

## The facade

`Packstub\AccountSwitcher\Facades\AccountSwitcher` proxies the `AccountSwitcher` service:

| Method | Purpose |
| --- | --- |
| `routes($prefix, $middleware)` | Register the four routes |
| `canImpersonate($by, $target)` / `impersonate($target)` / `isImpersonating()` / `impersonator()` / `stopImpersonating()` | Impersonation |
| `canSwitchToLinkedAccount($from, $to)` / `requiresPassword($from, $to)` / `switchToLinkedAccount($to, $password)` / `switchableAccounts($user)` | Linked accounts |
| `passwordMatches($account, $password)` | Verify a plain-text password against an account |
| `developerLoginsEnabled()` / `developerLoginUsers()` / `searchDeveloperLogins($search)` / `isDeveloperLoginAllowed($user, $search)` / `developerLogin($user, $search)` / `developerLoginRedirectUrl($user)` | Developer logins |
| `redirectUrlFor($user, $reason)` / `redirectWhenPageIsInaccessible($exception, $request)` | Where to land after a switch; the landing-page fallback |
| `log($from, $to, $reason, $context)` / `AccountSwitcher::resolveLogContextUsing($callback)` | The switch log |
| `context()` | The bound `SwitchContext` |
| `userModel()` / `connectionName()` / `resolveUsers($users)` | The resolved user model class, the tables' connection, users from a closure or an e-mail list |

Denied operations throw `Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied` (an `AuthorizationException`).

## Your own context

Everything that depends on *where* a switch happens is behind the `Packstub\AccountSwitcher\Contracts\SwitchContext` contract: the guard, the scope written to the log, user labels, access, redirects and the landing-page fallback, the feature toggles, the extra rules, the developer login list with its gate, labels, tags, search and landing page, the log context, and the URLs the two components post to. The package binds `AppContext`; the Filament package binds a context that answers from the current panel. Bind your own when your app has several places to sign in, and build it on `AppContext`: a method added to the contract in a minor then comes with its default.

```php
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Contracts\SwitchContext;

class SupportConsoleContext extends AppContext
{
    public function scope(): ?string
    {
        return 'support-console';
    }

    public function guardName(): string
    {
        return 'staff';
    }
}

$this->app->singleton(SwitchContext::class, SupportConsoleContext::class);
```

## Translations and views

```bash
php artisan vendor:publish --tag="account-switcher-translations"
php artisan vendor:publish --tag="account-switcher-views"
```

Strings live under `lang/vendor/account-switcher/{locale}/messages.php` (banner, developer logins heading, reasons, errors); views under `resources/views/vendor/account-switcher/components/` (`banner`, `developer-logins`).
