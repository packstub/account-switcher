# Changelog

All notable changes to `packstub/account-switcher` are documented here.

## 1.0.0 — 2026-10-27

The session core of [Filament Account Switcher](https://github.com/packstub/filament-account-switcher) 4.0, carved out so a Laravel app without Filament can use it, and so the Filament plugin builds on one tested path.

### Added

- `AccountSwitcher` service and facade: `impersonate()`, `stopImpersonating()`, `switchToLinkedAccount()`, `switchableAccounts()`, `developerLogin()`, `redirectUrlFor()`, the `canImpersonate()` / `canBeImpersonated()` hooks on the user model, `AccountSwitching` and `AccountSwitched` events, the `account_switches` audit log and the `linked_accounts` table with the `HasLinkedAccounts` trait.
- `SwitchContext` contract: where a switch happens (guard, redirects, feature toggles, extra rules, the developer login list). `AppContext` answers from the config file and from closures registered on it (`canImpersonateUsing`, `canSwitchUsing`, `canAccessUsing`, `redirectTo`, `userLabelUsing`, `developerLoginUsersUsing`). A Filament panel binds its own context.
- `AccountSwitcher::routes()`: `account-switcher.impersonate`, `.switch-back`, `.switch` (authenticated) and `.developer-login` (guest, throttled), inside the route group it is called from.
- Blade components `<x-account-switcher::banner />` and `<x-account-switcher::developer-logins />` with inline styles, for any layout.
- Runs without its migrations when linked accounts and the switch log are off (`features.linked_accounts`, `log_switches`): impersonation and developer logins keep their state in the session, and no code path touches the tables. See [Installation](docs/installation.md#without-migrations).
- Config: `guard`, `features`, `redirect_to`, `login_url`, `developer_logins.users`, next to the keys the Filament plugin already had (`user_model`, `connection`, `tables`, `log_switches`, `developer_logins.environments`, `banner`).
