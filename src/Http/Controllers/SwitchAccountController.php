<?php

namespace Packstub\AccountSwitcher\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;

/**
 * POST account-switcher/switch with `account` (the linked account's key) and,
 * when the link asks for it, `password` (that account's password).
 */
class SwitchAccountController
{
    public function __invoke(Request $request, AccountSwitcher $switcher): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required'],
            'password' => ['nullable', 'string'],
        ]);

        $target = $switcher->switchableAccounts($request->user())->first(
            fn ($account): bool => (string) $account->getAuthIdentifier() === (string) $validated['account'],
        );

        if (! $target) {
            throw AccountSwitchDenied::notLinked();
        }

        try {
            $switcher->switchToLinkedAccount($target, $validated['password'] ?? null);
        } catch (AccountSwitchDenied $exception) {
            // A wrong password is a form error, not a 403: the user stays on the form.
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        }

        return redirect()->to($switcher->redirectUrlFor($target));
    }
}
