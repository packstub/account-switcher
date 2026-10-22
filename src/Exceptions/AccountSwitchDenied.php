<?php

namespace Packstub\AccountSwitcher\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class AccountSwitchDenied extends AuthorizationException
{
    public static function notLinked(): self
    {
        return new self(__('account-switcher::messages.errors.not_linked'));
    }

    public static function invalidPassword(): self
    {
        return new self(__('account-switcher::messages.errors.invalid_password'));
    }

    public static function whileImpersonating(): self
    {
        return new self(__('account-switcher::messages.errors.while_impersonating'));
    }

    public static function cannotImpersonate(): self
    {
        return new self(__('account-switcher::messages.errors.cannot_impersonate'));
    }

    public static function developerLoginsDisabled(): self
    {
        return new self(__('account-switcher::messages.errors.developer_logins_disabled'));
    }

    public static function featureDisabled(): self
    {
        return new self(__('account-switcher::messages.errors.feature_disabled'));
    }
}
