<?php

namespace Packstub\AccountSwitcher\Enums;

enum SwitchReason: string
{
    case Impersonation = 'impersonation';
    case ImpersonationEnded = 'impersonation_ended';
    case LinkedAccount = 'linked_account';
    case DeveloperLogin = 'developer_login';

    public function label(): string
    {
        return __('account-switcher::messages.reasons.'.$this->value);
    }
}
