<?php

declare(strict_types=1);

use BBSLab\NovaPasswordRotation\NovaPasswordRotationServiceProvider;

it('boots the service provider and loads the config', function (): void {
    expect(app()->getProvider(NovaPasswordRotationServiceProvider::class))->not->toBeNull()
        ->and(config('nova-password-rotation.route_prefix'))->toBe('password-rotation')
        ->and(config('nova-password-rotation.expiry_action'))->toBe('change');
});
