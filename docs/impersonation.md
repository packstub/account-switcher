# Impersonation

Sign in as another user to see what they see, then switch back. Impersonation is for support and debugging; for your *own* accounts use [linked accounts](linked-accounts.md).

## Starting

A `POST` to `account-switcher.impersonate` with the user's key, or the facade:

```php
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

if (AccountSwitcher::canImpersonate(auth()->user(), $user)) {
    AccountSwitcher::impersonate($user);

    return redirect()->to(AccountSwitcher::redirectUrlFor($user));
}
```

`impersonate()` re-authenticates the session as the target, stores the impersonator in the session, writes an audit row and dispatches the events. It throws `AccountSwitchDenied` (an `AuthorizationException`, a 403 when uncaught) when not allowed; the route answers 404 for an unknown user.

Where the browser lands afterwards is `redirect_to` in the config, `redirectTo()` on the app context, or the home page. See [Configuration](configuration.md#redirects).

## Authorization

`canImpersonate()` is false, and `impersonate()` throws, unless **all** of these pass:

1. The target is not the current user.
2. The session is not already impersonating (no nesting).
3. Impersonation is on (`features.impersonation` in the config).
4. `canImpersonate($target)` on the current user returns true, if the method exists.
5. `canBeImpersonated($by)` on the target returns true, if the method exists.
6. The app context's `canImpersonateUsing()` callback returns true, if set.

```php
class User extends Authenticatable
{
    public function canImpersonate(User $target): bool
    {
        return $this->hasRole('super-admin');
    }

    public function canBeImpersonated(User $by): bool
    {
        return ! $this->hasRole('super-admin');
    }
}
```

> [!IMPORTANT]
> Without `canImpersonate()` on your model, every signed-in user can impersonate. Add it before going to production, or register a rule on the app context.

```php
use Packstub\AccountSwitcher\AppContext;

$this->app->make(AppContext::class)
    ->canImpersonateUsing(fn (User $by, User $target): bool => $by->can('impersonate', $target));
```

## The banner

```blade
<x-account-switcher::banner />
```

While impersonating, a fixed banner reads *"You are signed in as Jane, impersonating from Admin"* with a **Switch back** button. It renders nothing otherwise, so it can sit in the layout of every page. Names come from the `name` attribute, then the e-mail; `userLabelUsing()` on the app context changes that.

```blade
<x-account-switcher::banner position="top" style="light" />
```

To restyle it completely, publish the views and edit `resources/views/vendor/account-switcher/components/banner.blade.php`. The banner uses inline styles only, so it needs no build step and works with any CSS setup.

## Switching back

**Switch back** posts to `account-switcher.switch-back`, a CSRF-protected route behind `auth`. It restores the impersonator's session, logs the end of the impersonation and returns to the page the impersonation started from.

Programmatically:

```php
AccountSwitcher::isImpersonating();  // bool
AccountSwitcher::impersonator();     // ?Authenticatable
$url = AccountSwitcher::stopImpersonating();
```

If the impersonator no longer exists, the session is signed out and `stopImpersonating()` returns `login_url` from the config, the `login` route when there is one, or the home page.

## Turning it off

```php
// config/packstub-account-switcher.php
'features' => ['impersonation' => false],
```

`canImpersonate()` returns false and `impersonate()` throws `AccountSwitchDenied`. The routes stay registered and answer 403.

## Events and log

Impersonating dispatches `AccountSwitching` then `AccountSwitched` with `SwitchReason::Impersonation`; switching back dispatches `AccountSwitched` with `SwitchReason::ImpersonationEnded`. Both write to the [switch log](configuration.md#the-switch-log). Listen to them to alert on impersonation of sensitive accounts or to invalidate API tokens.
