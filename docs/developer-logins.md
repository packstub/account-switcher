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

## How it is gated

Two independent checks must pass before a button renders or a login is accepted:

1. **Environment**: `developer_logins.environments`, `['local']` by default. This is a hard ceiling: no other setting can make the buttons appear in an environment that is not listed.
2. **Feature and list**: `features.developer_logins` must be on, and the user must be in the resolved list.

The login itself is a CSRF-protected `POST` to `account-switcher.developer-login`, throttled to 10 requests per minute. The controller re-runs both checks and returns 404 when either fails, so a stray button or a crafted request never signs anyone in.

## What happens on click

The guard is re-authenticated as the chosen user, the session is regenerated, an [audit row](configuration.md#the-switch-log) with reason `SwitchReason::DeveloperLogin` is written, and the browser is redirected to the configured landing page.

## Customizing the buttons

Publish the views and edit `resources/views/vendor/account-switcher/components/developer-logins.blade.php`:

```bash
php artisan vendor:publish --tag="account-switcher-views"
```

Or build your own from `AccountSwitcher::developerLoginUsers()` (empty unless every check passes) and `AccountSwitcher::context()->developerLoginUrl()`.
