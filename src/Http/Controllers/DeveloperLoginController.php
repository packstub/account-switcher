<?php

namespace Packstub\AccountSwitcher\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Packstub\AccountSwitcher\AccountSwitcher;

/**
 * POST account-switcher/developer-login with `user`: sign in as a listed account, in allowed environments
 * only. With `search`, an account the context's search returns for that text is accepted too.
 */
class DeveloperLoginController
{
    public function __invoke(Request $request, AccountSwitcher $switcher): RedirectResponse
    {
        abort_unless($switcher->developerLoginsEnabled(), 404);

        $validated = $request->validate([
            'user' => ['required'],
            'search' => ['nullable', 'string'],
        ]);

        $search = filled($request->input('search')) ? (string) $request->input('search') : null;
        $matches = fn (Authenticatable $candidate): bool => (string) $candidate->getAuthIdentifier() === (string) $validated['user'];

        $user = $switcher->developerLoginUsers()->first($matches)
            ?? ($search !== null ? $switcher->searchDeveloperLogins($search)->first($matches) : null);

        abort_unless($user !== null, 404);

        $switcher->developerLogin($user, $search);

        return redirect()->to($switcher->developerLoginRedirectUrl($user));
    }
}
