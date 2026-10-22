<?php

use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

beforeEach(function (): void {
    config()->set('packstub-account-switcher.features.developer_logins', true);
    config()->set('packstub-account-switcher.developer_logins.users', ['dev-alice@example.com']);
});

it('renders buttons only in allowed environments', function (): void {
    $dev = createUser(['email' => 'dev-alice@example.com']);
    createUser(['email' => 'customer@example.com']);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Developer logins');

    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Developer logins')
        ->assertSee($dev->name)
        ->assertSee(route('account-switcher.developer-login'))
        ->assertDontSee('customer@example.com');
});

it('stays off until the feature is enabled', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    config()->set('packstub-account-switcher.features.developer_logins', false);

    createUser(['email' => 'dev-alice@example.com']);

    expect(AccountSwitcher::developerLoginsEnabled())->toBeFalse();

    $this->get('/login')->assertOk()->assertDontSee('Developer logins');
});

it('signs in a listed user', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect(url('/'));

    expect(auth()->user()->is($dev))->toBeTrue()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::DeveloperLogin)
        ->and(AccountSwitch::query()->sole()->from_user_id)->toBeNull();
});

it('rejects users outside the list', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $customer = createUser(['email' => 'customer@example.com']);

    $this->post(route('account-switcher.developer-login'), ['user' => $customer->id])
        ->assertNotFound();

    expect(auth()->user())->toBeNull();
});

it('is unreachable outside allowed environments', function (): void {
    $dev = createUser(['email' => 'dev-alice@example.com']);

    expect(AccountSwitcher::developerLoginsEnabled())->toBeFalse();

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])
        ->assertNotFound();

    expect(auth()->user())->toBeNull();
});

it('takes the users from a closure on the app context', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);

    $admin = createAdmin();
    createUser(['email' => 'dev-alice@example.com']);

    app(AppContext::class)->developerLoginUsersUsing(fn () => User::query()->where('is_admin', true)->get());

    expect(AccountSwitcher::developerLoginUsers()->pluck('id')->all())->toBe([$admin->id]);
});

it('offers the first ten users when none are listed', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    config()->set('packstub-account-switcher.developer_logins.users', null);

    foreach (range(1, 12) as $i) {
        createUser();
    }

    expect(AccountSwitcher::developerLoginUsers())->toHaveCount(10);
});
