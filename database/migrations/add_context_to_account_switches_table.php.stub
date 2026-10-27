<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packstub\AccountSwitcher\AccountSwitcher;

/**
 * Installs that predate the `context` column get it here; a fresh install has
 * it from create_account_switches_table and this migration does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('packstub-account-switcher.tables.account_switches', 'account_switches');
        $schema = Schema::connection(AccountSwitcher::connectionName());

        if (! $schema->hasTable($table) || $schema->hasColumn($table, 'context')) {
            return;
        }

        $schema->table($table, function (Blueprint $table): void {
            $table->json('context')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        $table = config('packstub-account-switcher.tables.account_switches', 'account_switches');
        $schema = Schema::connection(AccountSwitcher::connectionName());

        if ($schema->hasTable($table) && $schema->hasColumn($table, 'context')) {
            $schema->table($table, function (Blueprint $table): void {
                $table->dropColumn('context');
            });
        }
    }
};
