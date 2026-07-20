<?php

declare(strict_types=1);

use BBSLab\NovaPasswordRotation\Rules\PasswordNotReused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Translation\PotentiallyTranslatedString;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

/**
 * @return array{0: bool, 1: ?string}
 */
function runReuseRule(PasswordNotReused $rule, mixed $value): array
{
    $message = null;

    $rule->validate('password', $value, function (string $msg) use (&$message): PotentiallyTranslatedString {
        $message = $msg;

        return new PotentiallyTranslatedString($msg, app('translator'));
    });

    return [$message !== null, $message];
}

it('fails when the password was used before', function (): void {
    config(['nova-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create(); // password === 'password'

    [$failed] = runReuseRule(new PasswordNotReused($user), 'password');

    expect($failed)->toBeTrue();
});

it('passes when the password is new', function (): void {
    config(['nova-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create();

    [$failed] = runReuseRule(new PasswordNotReused($user), 'a-genuinely-new-secret');

    expect($failed)->toBeFalse();
});

it('passes when the value is not a string', function (): void {
    config(['nova-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create();

    [$failed] = runReuseRule(new PasswordNotReused($user), 12345);

    expect($failed)->toBeFalse();
});
