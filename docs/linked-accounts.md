# Linked accounts

Linked accounts let one person hold several accounts and move between them without signing out. The typical setup is a **full admin account** you rarely touch and a **daily account** with fewer permissions. The package makes the safe account the convenient one.

## Linking

The trait on your user model:

```php
$admin->linkAccount($daily, label: 'Daily', requiresPassword: false);
$admin->unlinkAccount($daily);
$admin->isLinkedTo($daily);          // bool
$admin->linkedAccounts;              // BelongsToMany, pivot has label + requires_password
```

Links are symmetric: both accounts can switch to each other. Each direction has its own label and password rule, stored on the `linked_accounts` pivot. Linking twice updates the label and the rule instead of duplicating the row.

When you build a page that links accounts, ask for the other account's e-mail **and password** before calling `linkAccount()`, so a user can only ever link accounts they can already sign in to:

```php
$account = User::query()->where('email', $request->email)->first();

if (! $account || $account->is($request->user()) || ! AccountSwitcher::passwordMatches($account, $request->password)) {
    throw ValidationException::withMessages(['email' => 'No account matches those credentials.']);
}

$request->user()->linkAccount($account, label: $request->label, requiresPassword: $request->boolean('requires_password'));
```

## The "Switch to" list

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

AccountSwitcher::switchableAccounts(auth()->user());   // Collection of users, empty while impersonating
AccountSwitcher::requiresPassword($from, $to);          // whether that link asks for $to's password
```

A menu built from it:

```blade
@foreach (AccountSwitcher::switchableAccounts(auth()->user()) as $account)
    <form method="POST" action="{{ route('account-switcher.switch') }}">
        @csrf
        <input type="hidden" name="account" value="{{ $account->getKey() }}">

        @if (AccountSwitcher::requiresPassword(auth()->user(), $account))
            <input type="password" name="password" placeholder="Password of {{ $account->name }}" required>
        @endif

        <button type="submit">{{ $account->pivot->label ?: $account->name }}</button>
    </form>
@endforeach
```

`POST account-switcher.switch` takes `account` (the key) and `password`. An account that is not linked answers 403; a wrong or missing password comes back as a validation error on `password`, so the user stays on the form.

From code:

```php
AccountSwitcher::canSwitchToLinkedAccount($from, $to);   // bool
AccountSwitcher::switchToLinkedAccount($to, $password);  // throws AccountSwitchDenied
AccountSwitcher::redirectUrlFor($to);                    // where to send the browser next
```

`switchToLinkedAccount()` re-authenticates the guard as the target, regenerates the session, writes an [audit row](configuration.md#the-switch-log), and dispatches `AccountSwitching` then `AccountSwitched` with reason `SwitchReason::LinkedAccount`.

## The password rule

Each direction of a link has its own *requires password* flag:

- When **you** create the link (from account A to account B), you choose whether switching A to B asks for B's password.
- The reverse direction (B to A) **always requires A's password** until someone signed in as B relaxes it.

In practice: create your daily account from the admin account with the flag off, and you get one-click *down*, password-protected *up*. The password asked for is always the **target** account's, so a compromised low-privilege session cannot reach the admin account without the admin password.

## Built-in checks

`canSwitchToLinkedAccount()` is false unless:

1. The session is not impersonating (see [Security](security.md#no-escalation-through-impersonation)).
2. The target is a different account, linked to the current one.
3. Linked accounts are on (`features.linked_accounts`).
4. The target may sign in here: `canAccessUsing()` on the app context, which defaults to yes.
5. The app context's `canSwitchUsing()` callback agrees, if set.

```php
use Packstub\AccountSwitcher\AppContext;

$this->app->make(AppContext::class)
    ->canAccessUsing(fn (User $user): bool => $user->is_active)
    ->canSwitchUsing(fn (User $from, User $to): bool => ! $to->is_locked && $to->two_factor_confirmed_at !== null);
```
