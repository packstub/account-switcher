<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\AccountSwitcher\Tests\Fixtures\CentralUser;

it('links symmetrically and idempotently', function (): void {
    $admin = createAdmin();
    $daily = createUser();

    $admin->linkAccount($daily, label: 'Daily', requiresPassword: false);
    $admin->linkAccount($daily, label: 'Daily work');

    expect($admin->isLinkedTo($daily))->toBeTrue()
        ->and($daily->isLinkedTo($admin))->toBeTrue()
        ->and($admin->linkedAccounts()->count())->toBe(1)
        ->and($admin->linkedAccounts()->first()->pivot->label)->toBe('Daily work');

    $admin->unlinkAccount($daily);

    expect($admin->isLinkedTo($daily))->toBeFalse()
        ->and($daily->isLinkedTo($admin))->toBeFalse();
});

it('switches down without a password requirement', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $admin->linkAccount($daily, requiresPassword: false);

    $this->actingAs($admin);

    expect(AccountSwitcher::requiresPassword($admin, $daily))->toBeFalse()
        ->and(AccountSwitcher::switchableAccounts($admin)->pluck('id')->all())->toBe([$daily->id]);

    AccountSwitcher::switchToLinkedAccount($daily);

    expect(auth()->user()->is($daily))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse()
        ->and(AccountSwitch::query()->sole()->reason)->toBe(SwitchReason::LinkedAccount);
});

it('requires the target password when switching up', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $daily->linkAccount($admin, requiresPassword: true);

    $this->actingAs($daily);

    expect(AccountSwitcher::requiresPassword($daily, $admin))->toBeTrue();

    expect(fn () => AccountSwitcher::switchToLinkedAccount($admin, 'wrong'))
        ->toThrow(AccountSwitchDenied::class);

    expect(auth()->user()->is($daily))->toBeTrue();

    AccountSwitcher::switchToLinkedAccount($admin, 'secret');

    expect(auth()->user()->is($admin))->toBeTrue();
});

it('switches through the route', function (): void {
    $admin = createAdmin();
    $daily = createUser();
    $daily->linkAccount($admin, requiresPassword: true);

    $this->actingAs($daily);

    $this->from('/dashboard')
        ->post(route('account-switcher.switch'), ['account' => $admin->id, 'password' => 'wrong'])
        ->assertRedirect('/dashboard')
        ->assertSessionHasErrors('password');

    expect(auth()->user()->is($daily))->toBeTrue();

    $this->post(route('account-switcher.switch'), ['account' => $admin->id, 'password' => 'secret'])
        ->assertRedirect(url('/'));

    expect(auth()->user()->is($admin))->toBeTrue();
});

it('answers the route with 403 for an account that is not linked', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user)
        ->post(route('account-switcher.switch'), ['account' => $other->id, 'password' => 'secret'])
        ->assertForbidden();

    expect(auth()->user()->is($user))->toBeTrue();
});

it('cannot switch to an unlinked account', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();

    AccountSwitcher::switchToLinkedAccount($other, 'secret');
})->throws(AccountSwitchDenied::class);

it('cannot switch while impersonating', function (): void {
    $admin = createAdmin();
    $user = createUser();
    $userAdmin = createAdmin();
    $user->linkAccount($userAdmin, requiresPassword: false);

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $userAdmin))->toBeFalse()
        ->and(AccountSwitcher::switchableAccounts($user))->toBeEmpty();

    AccountSwitcher::switchToLinkedAccount($userAdmin);
})->throws(AccountSwitchDenied::class);

it('cannot switch to an account the context says may not sign in', function (): void {
    app(AppContext::class)->canAccessUsing(fn ($user): bool => ! $user->is_locked);

    $user = createUser();
    $locked = createUser(['is_locked' => true]);
    $user->linkAccount($locked, requiresPassword: false);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $locked))->toBeFalse();
});

it('lets the app context deny switching', function (): void {
    $user = createUser();
    $other = createUser();
    $user->linkAccount($other, requiresPassword: false);

    app(AppContext::class)->canSwitchUsing(fn (): bool => false);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();
});

it('can be turned off', function (): void {
    config()->set('packstub-account-switcher.features.linked_accounts', false);

    $user = createUser();
    $other = createUser();
    $user->linkAccount($other, requiresPassword: false);

    expect(AccountSwitcher::canSwitchToLinkedAccount($user, $other))->toBeFalse();
});

it('keeps the switch log on the user model connection', function (): void {
    // A second connection standing in for a tenant database: it becomes the
    // default, as a multi-database tenancy package would make it mid-request.
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('database.default', 'tenant');
    config()->set('packstub-account-switcher.user_model', CentralUser::class);

    $admin = CentralUser::query()->create(['name' => 'Admin', 'email' => 'admin@central.test', 'password' => Hash::make('secret'), 'is_admin' => true]);
    $daily = CentralUser::query()->create(['name' => 'Daily', 'email' => 'daily@central.test', 'password' => Hash::make('secret')]);
    $admin->linkAccount($daily, requiresPassword: false);

    $this->actingAs($admin);

    expect(AccountSwitcher::connectionName())->toBe('sqlite')
        ->and((new AccountSwitch)->getConnectionName())->toBe('sqlite');

    AccountSwitcher::switchToLinkedAccount($daily);

    expect(AccountSwitch::query()->count())->toBe(1)
        ->and(Schema::connection('tenant')->hasTable((new AccountSwitch)->getTable()))->toBeFalse();
});

it('pins the tables to the configured connection', function (): void {
    config()->set('database.connections.audit', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('packstub-account-switcher.connection', 'audit');

    (include __DIR__.'/../../database/migrations/create_account_switches_table.php.stub')->up();

    expect((new AccountSwitch)->getConnectionName())->toBe('audit')
        ->and(Schema::connection('audit')->hasTable((new AccountSwitch)->getTable()))->toBeTrue();
});
