# Changelog

All notable changes to `packstub/account-switcher` are documented here.

## 1.1.0 — 2026-10-27

What Filament Account Switcher 4.1 to 4.2.1 added on top of the 4.0 core this package was carved from, so the Filament plugin's 4.3 builds on it. A plain Laravel app gets the same features through the app context.

### Added

- **Developer logins**: `developerLoginsWhen()` on the app context, a runtime gate on top of the environment list, checked before the buttons render and again when a login is posted; `developerLoginLabelUsing()` and `developerLoginGroupUsing()` for the buttons' labels and the tags they are grouped under; `developerLoginSearchUsing()` to find any account for a menu of your own (`AccountSwitcher::searchDeveloperLogins()` and `isDeveloperLoginAllowed()`; the login route accepts `search` next to `user`, and only an account the current search returns); `developerLoginRedirectTo()` and `AccountSwitcher::developerLoginRedirectUrl()` for a landing page of their own.
- **`redirectUrlFor($user, $reason)`**: the switch reason reaches the redirect rule, so `redirectTo(fn ($user, ?SwitchReason $reason))` can send an impersonation and a developer login to different places. The controllers pass it.
- **Landing-page fallback**: `fallbackTo()` on the app context; a 403 or 404 on the page the browser lands on right after a switch is answered with a redirect there, once (`AccountSwitcher::redirectWhenPageIsInaccessible()`, hooked into the exception handler). Off until set.
- **Switch-log context**: a nullable `context` JSON column on `account_switches`, filled from `AccountSwitcher::resolveLogContextUsing()` (app-wide) and `switchLogContextUsing()` on the context; `AccountSwitcher::log($from, $to, $reason, $context)` writes a row for a switch made elsewhere. The guarded `add_context_to_account_switches_table` migration adds the column to an existing install (`php artisan vendor:publish --tag="account-switcher-migrations"`, then `php artisan migrate`); a fresh install has it from `create_account_switches_table`.

### Changed

- `SwitchContext` gained the methods behind the above (`allowsDeveloperLogins()`, `developerLoginLabel()`, `developerLoginGroup()`, `searchesDeveloperLogins()`, `searchDeveloperLogins()`, `developerLoginRedirectUrlFor()`, `fallbackUrlFor()`, `switchLogContext()`) and `redirectUrlFor()` takes the optional reason. A context built on `AppContext` inherits the defaults; one that implements the interface directly adds them.

## 1.0.0 — 2026-10-27

The session core of [Filament Account Switcher](https://github.com/packstub/filament-account-switcher) 4.0, carved out so a Laravel app without Filament can use it, and so the Filament plugin builds on one tested path.

### Added

- `AccountSwitcher` service and facade: `impersonate()`, `stopImpersonating()`, `switchToLinkedAccount()`, `switchableAccounts()`, `developerLogin()`, `redirectUrlFor()`, the `canImpersonate()` / `canBeImpersonated()` hooks on the user model, `AccountSwitching` and `AccountSwitched` events, the `account_switches` audit log and the `linked_accounts` table with the `HasLinkedAccounts` trait.
- `SwitchContext` contract: where a switch happens (guard, redirects, feature toggles, extra rules, the developer login list). `AppContext` answers from the config file and from closures registered on it (`canImpersonateUsing`, `canSwitchUsing`, `canAccessUsing`, `redirectTo`, `userLabelUsing`, `developerLoginUsersUsing`). A Filament panel binds its own context.
- `AccountSwitcher::routes()`: `account-switcher.impersonate`, `.switch-back`, `.switch` (authenticated) and `.developer-login` (guest, throttled), inside the route group it is called from.
- Blade components `<x-account-switcher::banner />` and `<x-account-switcher::developer-logins />` with inline styles, for any layout.
- Runs without its migrations when linked accounts and the switch log are off (`features.linked_accounts`, `log_switches`): impersonation and developer logins keep their state in the session, and no code path touches the tables. See [Installation](docs/installation.md#without-migrations).
- Config: `guard`, `features`, `redirect_to`, `login_url`, `developer_logins.users`, next to the keys the Filament plugin already had (`user_model`, `connection`, `tables`, `log_switches`, `developer_logins.environments`, `banner`).
