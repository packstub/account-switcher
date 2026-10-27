<?php

use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Facades\AccountSwitcher as AccountSwitcherFacade;

/**
 * The admin-only page is forbidden to the impersonated user, so it stands in for
 * any page the account just switched to may not see.
 */
it('sends a hidden landing page to the fallback URL, once', function (): void {
    app(AppContext::class)->fallbackTo(fn ($user): string => route('dashboard'));

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(session()->get(AccountSwitcher::FALLBACK_URL_SESSION_KEY))->toBe(route('dashboard'));
    session()->save();

    $this->get(route('admin-only'))->assertRedirect(route('dashboard'));
    $this->get(route('admin-only'))->assertForbidden();
});

it('does not touch the landing page when it renders', function (): void {
    app(AppContext::class)->fallbackTo('/dashboard');

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);
    session()->save(); // ends the request the switch was made in, like the redirect does

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('admin-only'))->assertForbidden();
});

it('is off until the app context has a fallback', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(session()->has(AccountSwitcher::FALLBACK_URL_SESSION_KEY))->toBeFalse();

    $this->get(route('admin-only'))->assertForbidden();
});
