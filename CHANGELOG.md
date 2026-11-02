# Changelog

All notable changes to `packstub/account-switcher` are documented here.

## 1.1.0 — 2026-10-27

Everything Filament Account Switcher 4.1 to 4.2.1 added, now in the core, so a plain Laravel app gets it too.

- Developer logins: a runtime gate, your own labels and groups, search for any account, a landing page of their own
- The redirect rule gets the switch reason, so impersonation and developer logins can land in different places
- Optional fallback page when the page you land on after a switch returns a 403 or 404
- Extra context saved with each row of the switch log, and `AccountSwitcher::log()` for switches made elsewhere

**Upgrading:** publish and run the new migration that adds the switch log's `context` column (`php artisan vendor:publish --tag="account-switcher-migrations"`, then `php artisan migrate`). `SwitchContext` has new methods: a context built on `AppContext` gets the defaults, one that implements the interface directly must add them.

## 1.0.0 — 2026-10-27

First release: the session core of [Filament Account Switcher](https://github.com/packstub/filament-account-switcher) 4.0, for a Laravel app without Filament.

- Impersonation, linked sub-accounts and developer logins through the `AccountSwitcher` service
- An audit log of every switch, plus events before and after it
- A `SwitchContext` contract, with `AppContext` configured from the config file and closures
- Routes from `AccountSwitcher::routes()` and Blade components for the banner and the developer login buttons
- Works without migrations when linked accounts and the switch log are off

Docs: https://packstub.dev/docs/account-switcher
