@php
    use Packstub\AccountSwitcher\Facades\AccountSwitcher;

    $context = AccountSwitcher::context();
    $users = AccountSwitcher::developerLoginUsers();
    $action = $context->developerLoginUrl();
@endphp

@if ($action && $users->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'account-switcher-developer-logins']) }} style="margin-top: 1.5rem; display: grid; gap: 0.5rem;">
        <p style="font-size: 0.75rem; text-align: center; opacity: .7;">
            {{ __('account-switcher::messages.developer_logins.heading', ['environment' => app()->environment()]) }}
        </p>

        @foreach ($users as $user)
            <form method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="user" value="{{ $user->getAuthIdentifier() }}">

                <button type="submit" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: transparent; font: inherit; cursor: pointer;">
                    {{ $context->userLabel($user) }}
                    @if ($user->email ?? null)
                        <span style="opacity: .7;">({{ $user->email }})</span>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
@endif
