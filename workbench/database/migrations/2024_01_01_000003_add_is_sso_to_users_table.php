<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Demo flag: accounts provisioned through SSO, whose password is owned
            // by the identity provider and must skip the local rotation. Read by
            // the PasswordRotation::bypass() callback in the NovaServiceProvider.
            $table->boolean('is_sso')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_sso');
        });
    }
};
