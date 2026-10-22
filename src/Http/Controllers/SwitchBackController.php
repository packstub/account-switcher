<?php

namespace Packstub\AccountSwitcher\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Packstub\AccountSwitcher\AccountSwitcher;

/**
 * POST account-switcher/switch-back: end the impersonation and restore the impersonator.
 */
class SwitchBackController
{
    public function __invoke(AccountSwitcher $switcher): RedirectResponse
    {
        if (! $switcher->isImpersonating()) {
            $user = $switcher->context()->guard()->user();

            return redirect()->to($user ? $switcher->redirectUrlFor($user) : $switcher->context()->loginUrl());
        }

        return redirect()->to($switcher->stopImpersonating());
    }
}
