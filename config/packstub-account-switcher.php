<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that linked accounts and the switch log point to.
    | Leave null to use the model of the default auth provider
    | (config('auth.providers.users.model')).
    |
    */

    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The session guard a switch signs in on. Leave null for the default guard.
    | Inside a Filament panel the panel's own guard is used instead.
    |
    */

    'guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection the linked_accounts and account_switches tables live on.
    | Leave null to use the user model's connection, so multi-database
    | tenancy setups (e.g. a `CentralConnection` user model) keep both
    | tables next to the users table instead of on the tenant connection.
    |
    */

    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'linked_accounts' => 'linked_accounts',
        'account_switches' => 'account_switches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | What the app offers. Inside a Filament panel the plugin's own options
    | decide instead (AccountSwitcherPlugin::make()->impersonation() ...).
    |
    */

    'features' => [
        'impersonation' => true,
        'linked_accounts' => true,
        'developer_logins' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Where the browser lands after a switch (null = the home page), and where
    | it goes when an impersonation ends and the impersonator no longer
    | exists (null = the `login` route when there is one, else the home page).
    | AppContext::redirectTo() accepts a closure for a per-user decision.
    |
    */

    'redirect_to' => null,

    'login_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Switch log
    |--------------------------------------------------------------------------
    |
    | Every impersonation, linked-account switch, and developer login is
    | recorded in the account_switches table with IP and user agent.
    | Disable if you keep your own audit trail via the events.
    |
    */

    'log_switches' => true,

    /*
    |--------------------------------------------------------------------------
    | Developer logins
    |--------------------------------------------------------------------------
    |
    | The environments in which the one-click login buttons may ever render:
    | the hard server-side ceiling, whatever the feature toggle or a panel
    | says. `users` lists the accounts by e-mail; null offers the first ten
    | users. AppContext::developerLoginUsersUsing() accepts a closure instead.
    |
    */

    'developer_logins' => [
        'environments' => ['local'],
        'users' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation banner
    |--------------------------------------------------------------------------
    |
    | Position: 'top' or 'bottom'. Style: 'dark' or 'light'.
    |
    */

    'banner' => [
        'position' => 'bottom',
        'style' => 'dark',
    ],

];
