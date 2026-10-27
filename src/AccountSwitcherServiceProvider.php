<?php

namespace Packstub\AccountSwitcher;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Packstub\AccountSwitcher\Contracts\SwitchContext;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AccountSwitcherServiceProvider extends PackageServiceProvider
{
    public static string $name = 'account-switcher';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile('packstub-account-switcher')
            ->hasViews(static::$name)
            ->hasTranslations()
            ->hasMigrations(['create_linked_accounts_table', 'create_account_switches_table', 'add_context_to_account_switches_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('packstub/account-switcher');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AccountSwitcher::class);
        $this->app->singleton(AppContext::class);

        // A package that knows better (a Filament panel) binds its own context; this one is the fallback.
        if (! $this->app->bound(SwitchContext::class)) {
            $this->app->bind(SwitchContext::class, fn ($app) => $app->make(AppContext::class));
        }
    }

    public function packageBooted(): void
    {
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', static::$name);

        // The landing-page fallback: a 403 or 404 right after a switch is answered with a redirect.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->renderable(fn (HttpExceptionInterface $exception, Request $request) => app(AccountSwitcher::class)->redirectWhenPageIsInaccessible($exception, $request));
            }
        });
    }
}
