# Account Switcher for Laravel

Switch between accounts in a Laravel app without signing out, safely, in production. Free and open source (MIT).

- Repository: [github.com/packstub/account-switcher](https://github.com/packstub/account-switcher)
- Packagist: [packstub/account-switcher](https://packagist.org/packages/packstub/account-switcher)
- Support: [GitHub issues](https://github.com/packstub/account-switcher/issues)
- In a Filament panel: [Filament Account Switcher](https://packstub.dev/docs/filament-account-switcher) builds on this package.

## Features

- **[Impersonation](impersonation.md)**: a route and a facade to sign in as a user, a Blade banner with Switch back, rules on your model.
- **[Linked accounts](linked-accounts.md)**: work from a low-privilege account and switch up to admin only when you need it, with the admin password.
- **[Developer logins](developer-logins.md)**: one-click sign-in buttons for your seeded accounts, only in the environments you allow.
- **[Audit trail](configuration.md#the-switch-log)**: every switch logged with who, why and from where, plus events.
- **[Security](security.md)**: one session path for every switch, and a written threat model per feature.

## Guides

| Guide | What it covers |
| --- | --- |
| [Installation](installation.md) | Requirements, the install command, the user model, the routes and the Blade components |
| [Impersonation](impersonation.md) | Starting and ending an impersonation, the banner, authorization hooks |
| [Linked accounts](linked-accounts.md) | Linking, the "Switch to" list, the password rule, a form that switches |
| [Developer logins](developer-logins.md) | Login-page buttons for local development and how they are gated |
| [Configuration](configuration.md) | The config file, the app context and its closures, events, the switch log, the facade, your own context |
| [Security](security.md) | The threat model behind each feature and the guarantees the package makes |

## At a glance

```bash
composer require packstub/account-switcher
php artisan account-switcher:install
```

```php
// routes/web.php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::routes();
```

```blade
{{-- resources/views/layouts/app.blade.php --}}
<x-account-switcher::banner />
```

## Requirements

PHP 8.2+, Laravel 12 or 13, a session guard.

---

These pages are published at [packstub.dev/docs/account-switcher](https://packstub.dev/docs/account-switcher) from the package's `docs/` directory. Spotted a mistake? [Open a pull request](https://github.com/packstub/account-switcher).
