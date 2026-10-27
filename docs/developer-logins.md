# Developer logins

One-click sign-in buttons under the login form, for the accounts you seed in development. Pick a user, land in the app, no password typing while you build.

## Enabling

Developer logins are **off** by default. Turn them on and say which users appear:

```php
// config/packstub-account-switcher.php
'features' => [
    'developer_logins' => true,
],

'developer_logins' => [
    'environments' => ['local'],
    'users' => ['admin@example.com', 'editor@example.com', 'customer@example.com'],
],
```

`users` set to `null` offers the first ten users by key. For a query, register a closure on the app context:

```php
use Packstub\AccountSwitcher\AppContext;

$this->app->make(AppContext::class)
    ->developerLoginUsersUsing(fn () => User::query()->whereIn('role', ['admin', 'editor'])->get());
```

Then render the buttons on your login page:

```blade
<x-account-switcher::developer-logins />
```

The component renders nothing when the feature is off, the environment is not allowed, the route is not registered or the list is empty.

## Labels and tags

By default a button shows the user's label (the name, then the e-mail) and the e-mail. To show what the account *is* instead of who it is, and to group the buttons under a tag (its role, its team):

```php
$this->app->make(AppContext::class)
    ->developerLoginLabelUsing(fn (User $user): string => "{$user->role_label} ({$user->username})")
    ->developerLoginGroupUsing(fn (User $user): ?string => $user->roles->first()?->name);
```

## Finding any account

The login page lists the configured accounts only. A menu of your own, for developers already signed in, can offer a search box over *any* account: give the app context a search resolver and `AccountSwitcher::searchDeveloperLogins($search)` returns what it finds (without a resolver it filters the listed accounts by label, tag, name, e-mail and username):

```php
$this->app->make(AppContext::class)
    ->developerLoginSearchUsing(fn (string $search) => User::query()
        ->where(fn ($query) => $query
            ->whereLike('name', "%{$search}%", caseSensitive: false)
            ->orWhereLike('email', "%{$search}%", caseSensitive: false))
        ->limit(20)
        ->get());
```

`whereLike(..., caseSensitive: false)` is case-insensitive on every database Laravel supports. Keep the result small. To sign in as a found account, post `search` next to `user` to the login route (or call `AccountSwitcher::developerLogin($user, $search)`): a login is accepted only for an account the *current* search returns, and the resolver is never consulted outside the [gates](#how-it-is-gated) below.

## How it is gated

Three independent checks must pass before a button renders or a login is accepted:

1. **Environment**: `developer_logins.environments`, `['local']` by default. This is a hard ceiling: no other setting can make the buttons appear in an environment that is not listed.
2. **Feature and list**: `features.developer_logins` must be on, and the user must be in the resolved list (or found by the current search).
3. **`developerLoginsWhen()`**: an optional runtime gate on the app context, for a rule the environment list cannot express. A staging server that should let signed-in staff switch accounts but never advertise them on the login page:

```php
// config: 'environments' => ['local', 'staging']
$this->app->make(AppContext::class)
    ->developerLoginsWhen(fn (): bool => app()->environment('local') || auth()->check());
```

The login itself is a CSRF-protected `POST` to `account-switcher.developer-login`, throttled to 10 requests per minute. The controller re-runs every check and returns 404 when one fails, so a stray button or a crafted request never signs anyone in.

## What happens on click

The guard is re-authenticated as the chosen user, the session is regenerated, an [audit row](configuration.md#the-switch-log) with reason `SwitchReason::DeveloperLogin` is written, and the browser is redirected.

## Where it lands

`developerLoginRedirectTo()` on the app context if set, else the usual [switch redirect](configuration.md#redirects) (`redirectTo()` with `SwitchReason::DeveloperLogin`, else `redirect_to`, else the home page). If the landing page is hidden from the account, the [fallback](impersonation.md#landing-on-a-hidden-page) applies.

```php
$this->app->make(AppContext::class)
    ->developerLoginRedirectTo(fn (User $user): string => $user->is_admin ? '/admin' : '/app');
```

## Customizing the buttons

Publish the views and edit `resources/views/vendor/account-switcher/components/developer-logins.blade.php`:

```bash
php artisan vendor:publish --tag="account-switcher-views"
```

Or build your own from `AccountSwitcher::developerLoginUsers()` (empty unless every check passes), `AccountSwitcher::context()->developerLoginLabel($user)` / `developerLoginGroup($user)` and `AccountSwitcher::context()->developerLoginUrl()`.
