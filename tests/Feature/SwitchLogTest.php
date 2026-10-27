<?php

use Packstub\AccountSwitcher\AccountSwitcher;
use Packstub\AccountSwitcher\AppContext;
use Packstub\AccountSwitcher\Enums\SwitchReason;
use Packstub\AccountSwitcher\Facades\AccountSwitcher as AccountSwitcherFacade;
use Packstub\AccountSwitcher\Models\AccountSwitch;

afterEach(fn () => AccountSwitcher::resolveLogContextUsing(null));

it('records a switch made elsewhere with its context', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $row = AccountSwitcherFacade::log($admin, $user, SwitchReason::Impersonation, ['tenant' => 'acme']);

    expect($row)->toBeInstanceOf(AccountSwitch::class)
        ->and($row->from_user_id)->toBe($admin->id)
        ->and($row->to_user_id)->toBe($user->id)
        ->and($row->context)->toBe(['tenant' => 'acme'])
        ->and(AccountSwitch::query()->sole()->context)->toBe(['tenant' => 'acme']);
});

it('merges the app-wide and the context data into every row', function (): void {
    AccountSwitcher::resolveLogContextUsing(fn (SwitchReason $reason): array => ['tenant' => 'acme', 'via' => 'app']);
    app(AppContext::class)->switchLogContextUsing(fn (SwitchReason $reason): array => ['via' => 'context', 'reason' => $reason->value]);

    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(AccountSwitch::query()->sole()->context)->toBe(['tenant' => 'acme', 'via' => 'context', 'reason' => 'impersonation']);
});

it('leaves the context null when there is none', function (): void {
    $admin = createAdmin();
    $user = createUser();

    $this->actingAs($admin);
    AccountSwitcherFacade::impersonate($user);

    expect(AccountSwitch::query()->sole()->context)->toBeNull();
});

it('writes nothing when the log is off', function (): void {
    config()->set('packstub-account-switcher.log_switches', false);

    expect(AccountSwitcherFacade::log(createAdmin(), createUser(), SwitchReason::Impersonation))->toBeNull()
        ->and(AccountSwitch::query()->count())->toBe(0);
});
