<?php

use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

/**
 * An app that skipped the migrations: linked accounts and the switch log off,
 * neither table present.
 */
beforeEach(function (): void {
    config()->set('packstub-account-switcher.features.linked_accounts', false);
    config()->set('packstub-account-switcher.features.developer_logins', true);
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    config()->set('packstub-account-switcher.developer_logins.users', ['dev-alice@example.com']);
    config()->set('packstub-account-switcher.log_switches', false);

    Schema::drop('linked_accounts');
    Schema::drop('account_switches');
});

it('impersonates and switches back without the tables', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(auth()->user()->is($user))->toBeTrue();

    $this->post(route('account-switcher.switch-back'))->assertRedirect();

    expect(auth()->user()->is($admin))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('signs in with a developer login without the tables', function (): void {
    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])->assertRedirect();

    expect(auth()->user()->is($dev))->toBeTrue();
});

it('answers the linked-account questions without querying', function (): void {
    $user = createUser();
    $other = createUser();

    expect(AccountSwitcher::switchableAccounts($user))->toBeEmpty()
        ->and(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();
});
