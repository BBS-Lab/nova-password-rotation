<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;

/**
 * A reset-password notification whose link is set explicitly, so the email
 * points at Nova's own reset page instead of the framework default
 * route('password.reset') — which a Nova application does not define.
 */
class ResetPassword extends BaseNotification
{
    public string $url;

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
