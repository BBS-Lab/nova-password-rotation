<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed demo users covering every rotation state so the package can be tried
     * end-to-end with `composer serve` (all passwords are "password").
     *
     * Log in as nova@laravel.com to be redirected straight to the forced
     * change screen; log in as expiring@example.com to see the warning notice.
     */
    public function run(): void
    {
        // The default login user — expired 100 days ago (rotation window is 90).
        $this->stamp('Laravel Nova', 'nova@laravel.com', now()->subDays(100));

        // Valid but within the 7-day warning window (expires in ~5 days).
        $this->stamp('Expiring Soon', 'expiring@example.com', now()->subDays(85));

        // Freshly rotated — nothing to do.
        $this->stamp('Fresh User', 'fresh@example.com', now());

        // Never set — forced to change on first login when force_on_first_login.
        $this->stamp('First Login', 'first-login@example.com', null);
    }

    /**
     * Create the user (if missing) and set its rotation timestamp without
     * triggering the RotatesPassword model hooks, so the state stays as given.
     */
    private function stamp(string $name, string $email, ?CarbonInterface $changedAt): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        );

        $user->forceFill(['password_changed_at' => $changedAt])->saveQuietly();
    }
}
