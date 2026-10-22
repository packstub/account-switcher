<?php

use Illuminate\Support\Facades\Route;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;

it('binds the app context by default', function (): void {
    expect(AccountSwitcher::context())->toBeInstanceOf(AppContext::class)
        ->and(app(SwitchContext::class))->toBe(app(AppContext::class));
});

it('registers the routes under a prefix with the given middleware', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'account-switcher.'))
        ->mapWithKeys(fn ($route): array => [$route->getName() => [$route->uri(), $route->methods()[0], $route->gatherMiddleware()]]);

    expect($routes->keys()->sort()->values()->all())->toBe([
        'account-switcher.developer-login',
        'account-switcher.impersonate',
        'account-switcher.switch',
        'account-switcher.switch-back',
    ])
        ->and($routes['account-switcher.impersonate'][0])->toBe('account-switcher/impersonate/{user}')
        ->and($routes['account-switcher.impersonate'][1])->toBe('POST')
        ->and($routes['account-switcher.switch-back'][2])->toContain('auth')
        ->and($routes['account-switcher.developer-login'][2])->toContain('throttle:10,1')
        ->and($routes['account-switcher.developer-login'][2])->not->toContain('auth');
});

it('requires a signed-in user on the authenticated routes', function (): void {
    $this->post(route('account-switcher.switch-back'))
        ->assertRedirect(route('login'));
});

it('resolves the redirect from the context', function (): void {
    $user = createUser();

    expect(AccountSwitcher::redirectUrlFor($user))->toBe(url('/'));

    config()->set('packstub-account-switcher.redirect_to', '/home');

    expect(AccountSwitcher::redirectUrlFor($user))->toBe(url('/home'));

    app(AppContext::class)->redirectTo(fn ($user): string => '/users/'.$user->id);

    expect(AccountSwitcher::redirectUrlFor($user))->toBe(url('/users/'.$user->id));
});

it('signs in on the configured guard', function (): void {
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    config()->set('packstub-account-switcher.guard', 'staff');

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin, 'staff');
    AccountSwitcher::impersonate($user);

    expect(auth()->guard('staff')->user()->is($user))->toBeTrue()
        ->and(auth()->guard('web')->user())->toBeNull()
        ->and(AccountSwitch::query()->sole()->guard)->toBe('staff');

    AccountSwitcher::stopImpersonating();

    expect(auth()->guard('staff')->user()->is($admin))->toBeTrue();
});

it('honours a context bound by the app', function (): void {
    $context = new class extends AppContext
    {
        public function scope(): ?string
        {
            return 'support-console';
        }

        public function hasImpersonation(): bool
        {
            return false;
        }
    };

    app()->instance(SwitchContext::class, $context);

    $admin = createAdmin();
    $user = createUser();

    expect(AccountSwitcher::context())->toBe($context)
        ->and(AccountSwitcher::canImpersonate($admin, $user))->toBeFalse();
});

it('exposes the switch reason labels', function (): void {
    expect(SwitchReason::LinkedAccount->label())->toBe('Linked account');
});
