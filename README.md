# Account Switcher for Laravel

<div class="filament-hidden">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/packstub/account-switcher.svg?style=flat-square)](https://packagist.org/packages/packstub/account-switcher)
[![Tests](https://img.shields.io/github/actions/workflow/status/packstub/account-switcher/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/packstub/account-switcher/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/packstub/account-switcher.svg?style=flat-square)](https://packagist.org/packages/packstub/account-switcher)
[![License](https://img.shields.io/packagist/l/packstub/account-switcher.svg?style=flat-square)](https://github.com/packstub/account-switcher/blob/main/LICENSE.md)
[![Sponsor](https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?style=flat-square&logo=githubsponsors&logoColor=white)](https://github.com/sponsors/icaliman)

</div>

Switch between accounts in a Laravel app without signing out, safely, in production. Free and open source (MIT).

Impersonate a user to see what they see and switch back from a banner. Link the accounts one person owns and move between them, with the target account's password asked when switching up. Sign in with one click as a seeded account while you build. Every switch lands in an audit log and fires an event.

Works with any session guard: Breeze, Jetstream, Fortify, Inertia or your own login. [Filament Account Switcher](https://github.com/packstub/filament-account-switcher) puts the same features into a Filament panel.

- Docs: [packstub.dev/docs/account-switcher](https://packstub.dev/docs/account-switcher) (the same pages live in [`docs/`](docs/))
- Support: [GitHub issues](https://github.com/packstub/account-switcher/issues)

## Features

- **[Impersonation](docs/impersonation.md)**: a route and a facade to sign in as a user, a Blade banner with Switch back, rules on your model.
- **[Linked accounts](docs/linked-accounts.md)**: work from a low-privilege account and switch up to admin only when you need it, with the admin password.
- **[Developer logins](docs/developer-logins.md)**: one-click sign-in buttons for your seeded accounts, only in the environments you allow.
- **[Audit trail](docs/configuration.md#the-switch-log)**: every switch logged with who, why, guard, IP and user agent, plus `AccountSwitching` and `AccountSwitched` events.
- **[One session path](docs/security.md)**: every switch re-authenticates your guard, regenerates the session and never sets "remember me".
- **Multi-database aware**: the two tables follow your user model's connection, so they stay central under multi-database tenancy.

## Compatibility

| Package | Laravel | PHP |
| --- | --- | --- |
| 1.x | 12.x, 13.x | 8.2+ |

## Quick start

```bash
composer require packstub/account-switcher
php artisan account-switcher:install
```

Only need impersonation and developer logins? Set `features.linked_accounts` and `log_switches` to `false` and skip the migrations and the trait ([without migrations](https://packstub.dev/docs/account-switcher/installation#without-migrations)).

Add the trait and the rules to your user model:

```php
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;

class User extends Authenticatable
{
    use HasLinkedAccounts;

    public function canImpersonate(User $target): bool
    {
        return $this->is_admin;
    }

    public function canBeImpersonated(User $by): bool
    {
        return ! $this->is_admin;
    }
}
```

Register the routes in `routes/web.php`:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::routes();
```

Put the banner in your layout and, if you want them, the developer login buttons on your login page:

```blade
<x-account-switcher::banner />

<x-account-switcher::developer-logins />
```

Then, from a users table:

```blade
<form method="POST" action="{{ route('account-switcher.impersonate', $user) }}">
    @csrf
    <button type="submit">Impersonate</button>
</form>
```

Or from code:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::impersonate($user);
return redirect()->to(AccountSwitcher::redirectUrlFor($user));
```

Full walkthrough: [Installation](docs/installation.md).

## Linked accounts

```php
$admin->linkAccount($daily, label: 'Daily', requiresPassword: false);

AccountSwitcher::switchableAccounts($admin);                 // what the "Switch to" menu lists
AccountSwitcher::switchToLinkedAccount($daily);              // one click down
AccountSwitcher::switchToLinkedAccount($admin, $password);   // the admin password up
```

A `POST` to `account-switcher.switch` with `account` and `password` does the same from a form. Read more: [Linked accounts](docs/linked-accounts.md) and the [security model](docs/security.md).

## Configuration

What fits in config lives in `config/packstub-account-switcher.php` (guard, features, redirects, the developer login list, the environment ceiling, the tables). Rules that need code go on the app context, from a service provider's `boot()`:

```php
use Packstub\AccountSwitcher\AppContext;

$this->app->make(AppContext::class)
    ->canImpersonateUsing(fn (User $by, User $target): bool => $by->can('impersonate', $target))
    ->canSwitchUsing(fn (User $from, User $to): bool => ! $to->is_locked)
    ->canAccessUsing(fn (User $user): bool => $user->is_active)
    ->redirectTo(fn (User $user): string => route('dashboard'))
    ->userLabelUsing(fn (User $user): string => $user->full_name)
    ->developerLoginUsersUsing(fn () => User::query()->where('role', 'developer')->get());
```

Read more: [Configuration](docs/configuration.md), including events, the switch log and the facade.

## Documentation

- [Installation](docs/installation.md)
- [Impersonation](docs/impersonation.md)
- [Linked accounts](docs/linked-accounts.md)
- [Developer logins](docs/developer-logins.md)
- [Configuration](docs/configuration.md)
- [Security](docs/security.md)

## Testing

```bash
composer test
```

## Changelog

See the [changelog](CHANGELOG.md).

## Security vulnerabilities

Please e-mail [support@packstub.dev](mailto:support@packstub.dev) rather than opening a public issue.

## Credits

- [Ion Caliman](https://github.com/icaliman)
- [All contributors](https://github.com/packstub/account-switcher/contributors)

## License

MIT. See the [license file](LICENSE.md).
