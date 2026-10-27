# Installation

## Requirements

- PHP 8.2 or newer
- Laravel 12 or 13
- A session guard (the default `web` guard is fine)

## 1. Require the package

```bash
composer require packstub/account-switcher
php artisan account-switcher:install
```

The install command publishes the config file and the two migrations (`linked_accounts`, `account_switches`) and offers to run them. You can run the pieces yourself instead:

```bash
php artisan vendor:publish --tag="account-switcher-config"
php artisan vendor:publish --tag="account-switcher-migrations"
php artisan migrate
```

> [!NOTE]
> The migrations reference your user model's table through `config('auth.providers.users.model')`. If your guard authenticates a different model, set `user_model` in the config file **before** migrating. See [Configuration](configuration.md#user-model).

### Without migrations

Only need impersonation and one-click developer logins? Skip the migrations. Those two features keep their state in the session; only linked accounts (the `linked_accounts` table) and the switch log (the `account_switches` table) need the database. Turn both off in the config file:

```php
'features' => [
    'impersonation' => true,
    'linked_accounts' => false,
    'developer_logins' => true,
],

'log_switches' => false,
```

Then answer "no" when the install command offers to run the migrations (or publish the config file alone), and skip the `HasLinkedAccounts` trait in step 2. Either feature can come back later: run the migrations and flip the key. Without the log, the `AccountSwitched` event is your audit trail; see [Security](security.md#audit-trail).

## 2. Prepare the user model

Add the `HasLinkedAccounts` trait and the two authorization hooks:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;

class User extends Authenticatable
{
    use HasLinkedAccounts;

    /**
     * Who may impersonate other users. Optional; without it any signed-in
     * user can impersonate. Add it before going to production.
     */
    public function canImpersonate(User $target): bool
    {
        return $this->is_admin;
    }

    /**
     * Who may be impersonated. Optional.
     */
    public function canBeImpersonated(User $by): bool
    {
        return ! $this->is_admin;
    }
}
```

The trait adds the `linkedAccounts()` relationship and the `linkAccount()` / `unlinkAccount()` / `isLinkedTo()` helpers. Leave it out if you only want impersonation.

## 3. Register the routes

In `routes/web.php`, so the session and CSRF middleware apply:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::routes();
```

| Route | Name | Who |
| --- | --- | --- |
| `POST account-switcher/impersonate/{user}` | `account-switcher.impersonate` | signed in (`auth`) |
| `POST account-switcher/switch-back` | `account-switcher.switch-back` | signed in |
| `POST account-switcher/switch` | `account-switcher.switch` | signed in |
| `POST account-switcher/developer-login` | `account-switcher.developer-login` | guest, throttled 10/minute |

Change the prefix or the middleware of the signed-in routes: `AccountSwitcher::routes(prefix: 'admin/switch', middleware: ['auth:staff', 'verified'])`. Call it inside a route group to add a domain or more middleware.

## 4. Add the banner

Anywhere in the layout every signed-in page uses. It renders nothing unless the session is impersonating:

```blade
<x-account-switcher::banner />
```

The banner is fixed to the bottom of the page in the dark style; `position` and `style` in the config, or on the component (`<x-account-switcher::banner position="top" style="light" />`), move and restyle it. Publish the views to change the markup:

```bash
php artisan vendor:publish --tag="account-switcher-views"
```

## 5. Impersonate from your users page

A form that posts to the route:

```blade
@foreach ($users as $user)
    @if (AccountSwitcher::canImpersonate(auth()->user(), $user))
        <form method="POST" action="{{ route('account-switcher.impersonate', $user) }}">
            @csrf
            <button type="submit">Impersonate</button>
        </form>
    @endif
@endforeach
```

Or from a controller or a Livewire component:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::impersonate($user);

return redirect()->to(AccountSwitcher::redirectUrlFor($user));
```

## 6. Developer logins (optional)

Turn the feature on in the config, list the seeded accounts, and render the buttons on the login page:

```php
// config/packstub-account-switcher.php
'features' => ['developer_logins' => true],
'developer_logins' => [
    'environments' => ['local'],
    'users' => ['admin@example.com', 'customer@example.com'],
],
```

```blade
<x-account-switcher::developer-logins />
```

Continue with [Impersonation](impersonation.md).
