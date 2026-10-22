@props([
    'position' => config('packstub-account-switcher.banner.position', 'bottom'),
    'style' => config('packstub-account-switcher.banner.style', 'dark'),
])

@php
    use Packstub\AccountSwitcher\Facades\AccountSwitcher;

    $context = AccountSwitcher::context();
    $impersonated = $context->guard()->user();
    $impersonator = AccountSwitcher::impersonator();
    $switchBackUrl = $context->switchBackUrl();
    $dark = $style === 'dark';
@endphp

@if (AccountSwitcher::isImpersonating() && $impersonated)
    <style>
        .account-switcher-banner {
            position: fixed;
            {{ $position === 'top' ? 'top: 0;' : 'bottom: 0;' }}
            inset-inline: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            height: 3rem;
            padding-inline: 1rem;
            font-size: 0.875rem;
            font-family: ui-sans-serif, system-ui, sans-serif;
            background-color: {{ $dark ? '#111827' : '#f3f4f6' }};
            color: {{ $dark ? '#f9fafb' : '#111827' }};
            border-{{ $position === 'top' ? 'bottom' : 'top' }}: 1px solid {{ $dark ? '#374151' : '#d1d5db' }};
        }

        .account-switcher-banner form {
            display: inline;
        }

        .account-switcher-banner button {
            padding: 0.25rem 0.75rem;
            border: 0;
            border-radius: 0.375rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            background-color: {{ $dark ? '#f9fafb' : '#111827' }};
            color: {{ $dark ? '#111827' : '#f9fafb' }};
        }

        body {
            {{ $position === 'top' ? 'padding-top' : 'padding-bottom' }}: 3rem;
        }

        @media print {
            .account-switcher-banner {
                display: none;
            }
        }
    </style>

    <div {{ $attributes->merge(['class' => 'account-switcher-banner', 'role' => 'status']) }}>
        <span>
            {!! __('account-switcher::messages.banner.message', [
                'impersonated' => '<strong>'.e($context->userLabel($impersonated)).'</strong>',
                'impersonator' => '<strong>'.e($impersonator ? $context->userLabel($impersonator) : '—').'</strong>',
            ]) !!}
        </span>

        @if ($switchBackUrl)
            <form method="POST" action="{{ $switchBackUrl }}">
                @csrf
                <button type="submit">{{ __('account-switcher::messages.banner.switch_back') }}</button>
            </form>
        @endif
    </div>
@endif
