# packstub/account-switcher

**Account Switcher for Laravel**: user impersonation, linked sub-accounts (password-confirmed escalation), developer logins and an audit log of every switch, for any Laravel app with a session guard. Free, MIT. `packstub/filament-account-switcher` (the sibling repo, `plugins/filament-account-switcher`) puts the same features into a Filament panel and requires this package; the two share the `Packstub\AccountSwitcher\` namespace (Composer merges the PSR-4 directories), so class names must not collide across them.

## Commands

```bash
composer test               # Pest suite (Testbench, in-memory SQLite, no Filament, no Livewire)
composer test:filter <name>
composer lint               # Pint
```

## Layout

- `src/AccountSwitcher.php` + `Facades\AccountSwitcher`: the session core. Every switch goes through `authenticateAs()` (re-login on the context's guard, session regeneration, the per-guard `password_hash_*` keys cleared). `routes()` registers the four routes. `resolveUsers()` turns a closure, an e-mail list or null into users. Macroable, so the Filament package can add `plugin()` and `accessiblePanel()`.
- `src/Contracts/SwitchContext.php`: what differs between a plain app and a panel (guard, scope stored in the log's `panel` column, user labels, `canAccess`, redirects and the landing-page fallback URL, feature toggles, extra rules, the developer login list with its runtime gate, labels, tags, search and own redirect, the switch-log context, the two form URLs). A method added to the contract in a minor gets a default in `AppContext`, which a custom context should extend. `src/AppContext.php` is the plain-Laravel implementation: config keys plus fluent closures. The provider binds it only when nothing else is bound, so the Filament package's `PanelContext` wins whatever the provider order.
- `src/Http/Controllers`: `ImpersonateController` (403 via `AccountSwitchDenied`, 404 for an unknown user), `SwitchBackController`, `SwitchAccountController` (a wrong password is a validation error on `password`, not a 403), `DeveloperLoginController` (404 outside the environments or the list).
- `src/Models`: `LinkedAccount` (pivot, both directions as two rows), `AccountSwitch` (the log); both on `AccountSwitcher::connectionName()`. `src/Concerns/HasLinkedAccounts`, `src/Events`, `src/Enums/SwitchReason`, `src/Exceptions/AccountSwitchDenied` (an `AuthorizationException`).
- `resources/views/components`: `banner` and `developer-logins`, anonymous Blade components (`<x-account-switcher::banner />`), inline styles only. `resources/lang/en/messages.php`: `account-switcher::messages.*` (banner, developer logins heading, reasons, errors).
- `config/packstub-account-switcher.php`: the key is shared with the Filament package (its users published it under that name before the split); `database/migrations/*.stub`.
- `docs/`: customer docs, synced into the store under `account-switcher/1.x` by `docs-sync.yml`.

## Conventions

- PHP 8.2+, Laravel 12 and 13. Every change needs a Pest test and a `CHANGELOG.md` line; headings are `## <version> — <date>`, the tag is `v<version>` on `main`.
- Nothing here may import from `Filament\` or `Livewire\`; what needs a panel belongs in `packstub/filament-account-switcher`.
- Config keys, migration file names, the `packstub_account_switcher` session key, route names, the `SwitchContext` methods and the translation keys are shared with the Filament package; they must not change inside a major. Schema changes follow the workspace rule (final shape in `create_*`, guarded `add_*`).
- Authorization stays in the service, never only in a controller or a view: `canImpersonate()` and `canSwitchToLinkedAccount()` are the single source, the routes call the same methods.
- After a change here run the Filament package's suite too (`plugins/filament-account-switcher`, `composer test`).
