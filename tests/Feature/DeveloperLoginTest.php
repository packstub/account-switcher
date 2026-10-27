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

it('gates the buttons and the route with developerLoginsWhen', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    $dev = createUser(['email' => 'dev-alice@example.com']);

    app(AppContext::class)->developerLoginsWhen(fn (): bool => false);

    expect(AccountSwitcher::developerLoginsEnabled())->toBeFalse();

    $this->get('/login')->assertOk()->assertDontSee('Developer logins');

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])->assertNotFound();

    expect(auth()->user())->toBeNull();

    app(AppContext::class)->developerLoginsWhen(fn (): bool => true);

    $this->get('/login')->assertOk()->assertSee('Developer logins');
});

it('labels and groups the buttons from the app context', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    config()->set('packstub-account-switcher.developer_logins.users', ['dev-alice@example.com', 'dev-bob@example.com']);

    app(AppContext::class)
        ->developerLoginLabelUsing(fn (User $user): string => 'Role: '.$user->email)
        ->developerLoginGroupUsing(fn (User $user): ?string => $user->is_admin ? 'Admins' : 'Staff');

    createUser(['email' => 'dev-alice@example.com']);
    createAdmin(['email' => 'dev-bob@example.com']);

    $this->get('/login')
        ->assertOk()
        ->assertSeeInOrder(['Staff', 'Role: dev-alice@example.com', 'Admins', 'Role: dev-bob@example.com']);
});

it('passes the switch reason to redirectTo', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    app(AppContext::class)->redirectTo(fn (User $user, ?SwitchReason $reason): string => '/after/'.$reason?->value);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect('/after/developer_login');
});

it('lands a developer login on developerLoginRedirectTo', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    app(AppContext::class)
        ->redirectTo('/elsewhere')
        ->developerLoginRedirectTo(fn (User $user): string => '/landing/'.$user->id);

    $dev = createUser(['email' => 'dev-alice@example.com']);

    expect(AccountSwitcher::developerLoginRedirectUrl($dev))->toBe(url('/landing/'.$dev->id));

    $this->post(route('account-switcher.developer-login'), ['user' => $dev->id])
        ->assertRedirect('/landing/'.$dev->id);
});

it('filters the listed accounts by label, tag, name, e-mail and username', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    config()->set('packstub-account-switcher.developer_logins.users', ['dev-alice@example.com', 'dev-bob@example.com']);
    app(AppContext::class)->developerLoginGroupUsing(fn (User $user): ?string => $user->is_admin ? 'Admins' : null);

    $alice = createUser(['name' => 'Alice', 'email' => 'dev-alice@example.com']);
    $bob = createAdmin(['name' => 'Bob', 'email' => 'dev-bob@example.com']);

    expect(AccountSwitcher::searchDeveloperLogins('')->pluck('id')->all())->toBe([$alice->id, $bob->id])
        ->and(AccountSwitcher::searchDeveloperLogins('ALICE')->pluck('id')->all())->toBe([$alice->id])
        ->and(AccountSwitcher::searchDeveloperLogins('admins')->pluck('id')->all())->toBe([$bob->id])
        ->and(AccountSwitcher::searchDeveloperLogins('nobody'))->toBeEmpty();
});

it('finds any account through the search closure and accepts a login for it', function (): void {
    config()->set('packstub-account-switcher.developer_logins.environments', ['testing']);
    app(AppContext::class)->developerLoginSearchUsing(fn (string $search) => User::query()->where('email', 'like', "%{$search}%")->limit(5)->get());

    createUser(['email' => 'dev-alice@example.com']);
    $customer = createUser(['email' => 'customer@example.com']);

    expect(AccountSwitcher::searchDeveloperLogins('customer')->pluck('id')->all())->toBe([$customer->id])
        ->and(AccountSwitcher::isDeveloperLoginAllowed($customer))->toBeFalse()
        ->and(AccountSwitcher::isDeveloperLoginAllowed($customer, 'customer'))->toBeTrue()
        ->and(AccountSwitcher::isDeveloperLoginAllowed($customer, 'alice'))->toBeFalse();

    // The login page lists the configured accounts only; the search is for a menu of your own.
    $this->get('/login')->assertOk()->assertDontSee('customer@example.com');

    $this->post(route('account-switcher.developer-login'), ['user' => $customer->id])->assertNotFound();

    $this->post(route('account-switcher.developer-login'), ['user' => $customer->id, 'search' => 'customer'])
        ->assertRedirect(url('/'));

    expect(auth()->user()->is($customer))->toBeTrue();
});

it('keeps the search closure behind the environment gate', function (): void {
    app(AppContext::class)->developerLoginSearchUsing(fn (string $search) => User::query()->get());

    $customer = createUser(['email' => 'customer@example.com']);

    expect(AccountSwitcher::searchDeveloperLogins('customer'))->toBeEmpty();

    $this->post(route('account-switcher.developer-login'), ['user' => $customer->id, 'search' => 'customer'])->assertNotFound();
});
