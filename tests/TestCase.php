<?php

namespace Packstub\AccountSwitcher\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Packstub\AccountSwitcher\AccountSwitcherServiceProvider;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AccountSwitcherServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:2fl+Ktv6fZ7c7ZQfF1Zt6Q0Wd9jz5bJ6rKq8nX0m3Yk=');
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    /**
     * The routes a plain app would have: the package's own, a login page and
     * pages that render the two Blade components.
     */
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            AccountSwitcher::routes();

            Route::view('login', 'login')->name('login');
            Route::view('dashboard', 'dashboard')->middleware('auth')->name('dashboard');
        });
    }

    protected function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        foreach (['create_linked_accounts_table', 'create_account_switches_table'] as $migration) {
            (include __DIR__."/../database/migrations/{$migration}.php.stub")->up();
        }
    }
}
