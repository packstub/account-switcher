<?php

use Illuminate\Support\Facades\Event;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Events\AccountSwitched;
use Packstub\AccountSwitcher\Events\AccountSwitching;
use Packstub\AccountSwitcher\Exceptions\AccountSwitchDenied;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;

it('lets an admin impersonate a user and logs it', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    Event::fake([AccountSwitching::class, AccountSwitched::class]);

    AccountSwitcher::impersonate($user);

    expect(auth()->user()->is($user))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeTrue()
        ->and(AccountSwitcher::impersonator()->is($admin))->toBeTrue();

    $switch = AccountSwitch::query()->sole();

    expect($switch->from_user_id)->toBe($admin->id)
        ->and($switch->to_user_id)->toBe($user->id)
        ->and($switch->reason)->toBe(SwitchReason::Impersonation)
        ->and($switch->panel)->toBeNull()
        ->and($switch->guard)->toBe('web');

    Event::assertDispatched(AccountSwitching::class);
    Event::assertDispatched(AccountSwitched::class, fn (AccountSwitched $event): bool => $event->reason === SwitchReason::Impersonation && $event->to->is($user));
});

it('denies impersonation to non-admins', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user);

    expect(AccountSwitcher::canImpersonate($user, $other))->toBeFalse();

    AccountSwitcher::impersonate($other);
})->throws(AccountSwitchDenied::class);

it('does not let admins be impersonated or impersonate themselves', function (): void {
    $admin = createAdmin();
    $otherAdmin = createAdmin();

    expect(AccountSwitcher::canImpersonate($admin, $otherAdmin))->toBeFalse()
        ->and(AccountSwitcher::canImpersonate($admin, $admin))->toBeFalse();
});

it('denies nested impersonation', function (): void {
    $admin = createAdmin();
    $user = createUser();
    $another = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(AccountSwitcher::canImpersonate($user, $another))->toBeFalse();
});

it('lets the app context deny impersonation', function (): void {
    $admin = createAdmin();
    $user = createUser();

    app(AppContext::class)->canImpersonateUsing(fn (): bool => $user->email !== $user->email);

    expect(AccountSwitcher::canImpersonate($admin, $user))->toBeFalse();
});

it('can be turned off', function (): void {
    config()->set('packstub-account-switcher.features.impersonation', false);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);

    expect(AccountSwitcher::canImpersonate($admin, $user))->toBeFalse();

    AccountSwitcher::impersonate($user);
})->throws(AccountSwitchDenied::class);

it('impersonates through the route and lands on the redirect', function (): void {
    config()->set('packstub-account-switcher.redirect_to', '/dashboard');

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin)
        ->post(route('account-switcher.impersonate', $user))
        ->assertRedirect(url('/dashboard'));

    expect(auth()->user()->is($user))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeTrue();
});

it('answers the route with 403 when not allowed and 404 for an unknown user', function (): void {
    $user = createUser();
    $other = createUser();

    $this->actingAs($user)
        ->post(route('account-switcher.impersonate', $other))
        ->assertForbidden();

    $this->actingAs(createAdmin())
        ->post(route('account-switcher.impersonate', 999))
        ->assertNotFound();

    expect(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('restores the impersonator on switch back', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->post(route('account-switcher.switch-back'))
        ->assertRedirect();

    expect(auth()->user()->is($admin))->toBeTrue()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse()
        ->and(AccountSwitch::query()->count())->toBe(2)
        ->and(AccountSwitch::query()->latest('id')->first()->reason)->toBe(SwitchReason::ImpersonationEnded);
});

it('signs out and goes to the login page when the impersonator is gone', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);
    $admin->delete();

    expect(AccountSwitcher::stopImpersonating())->toBe(route('login'))
        ->and(auth()->check())->toBeFalse()
        ->and(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('renders the banner component while impersonating', function (): void {
    $admin = createAdmin(['name' => 'Grace Hopper']);
    $user = createUser(['name' => 'Ada Lovelace']);

    $this->actingAs($admin);

    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee('account-switcher-banner');

    AccountSwitcher::impersonate($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('account-switcher-banner')
        ->assertSee('Switch back')
        ->assertSee('Grace Hopper')
        ->assertSee('Ada Lovelace')
        ->assertSee(route('account-switcher.switch-back'));
});

it('names users in the banner through the app context', function (): void {
    app(AppContext::class)->userLabelUsing(fn ($user): string => strtoupper($user->email));

    $admin = createAdmin(['email' => 'grace@example.com']);
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('GRACE@EXAMPLE.COM');
});

it('can disable logging', function (): void {
    config()->set('packstub-account-switcher.log_switches', false);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcher::impersonate($user);

    expect(AccountSwitch::query()->count())->toBe(0);
});
