<?php

declare(strict_types=1);

use BBSLab\NovaPasswordRotation\Http\Controllers\PasswordRotationController;
use Illuminate\Support\Facades\Route;

Route::get('expired', [PasswordRotationController::class, 'show'])
    ->name('nova-password-rotation.expired.show');

Route::post('expired', [PasswordRotationController::class, 'update'])
    ->name('nova-password-rotation.expired.update');
