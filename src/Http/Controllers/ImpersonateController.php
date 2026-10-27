<?php

namespace Packstub\AccountSwitcher\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\Enums\SwitchReason;

/**
 * POST account-switcher/impersonate/{user}: sign in as the user, when allowed.
 */
class ImpersonateController
{
    public function __invoke(AccountSwitcher $switcher, string $user): RedirectResponse
    {
        $model = AccountSwitcher::userModel();
        $target = $model::query()->findOrFail($user);

        // Throws AccountSwitchDenied (an AuthorizationException, 403) when not allowed.
        $switcher->impersonate($target);

        return redirect()->to($switcher->redirectUrlFor($target, SwitchReason::Impersonation));
    }
}
