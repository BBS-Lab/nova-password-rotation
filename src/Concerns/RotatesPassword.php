<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Concerns;

use BBSLab\NovaPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\NovaPasswordRotation\Events\PasswordRotated;
use BBSLab\NovaPasswordRotation\Models\PasswordHistory;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Implements {@see MustRotatePassword}. Use on an Eloquent model that is also
 * Authenticatable.
 *
 * @mixin Model
 */
trait RotatesPassword
{
    public function passwordRotationColumn(): string
    {
        return (string) config('nova-password-rotation.column');
    }

    public function initializeRotatesPassword(): void
    {
        $this->mergeCasts([$this->passwordRotationColumn() => 'datetime']);
    }

    public function passwordLastChangedAt(): ?CarbonInterface
    {
        $value = $this->getAttribute($this->passwordRotationColumn());

        return $value instanceof CarbonInterface ? $value : null;
    }

    public function passwordExpiresAt(): ?CarbonInterface
    {
        return $this->passwordLastChangedAt()
            ?->copy()
            ->addDays((int) config('nova-password-rotation.days'));
    }

    public function passwordHasExpired(): bool
    {
        if (! config('nova-password-rotation.enabled')) {
            return false;
        }

        $expiresAt = $this->passwordExpiresAt();

        if ($expiresAt === null) {
            return (bool) config('nova-password-rotation.force_on_first_login');
        }

        return $expiresAt->isPast();
    }

    public static function bootRotatesPassword(): void
    {
        static::creating(function (Model&MustRotatePassword $model): void {
            // When first-login rotation is forced, leave the column null so a
            // freshly provisioned account is treated as expired until it sets
            // its own password. Auto-stamping here would silently grant a full
            // rotation window and make force_on_first_login a no-op.
            if (config('nova-password-rotation.force_on_first_login')) {
                return;
            }

            $column = $model->passwordRotationColumn();

            if (empty($model->getAttribute($column))) {
                $model->setAttribute($column, $model->freshTimestamp());
            }
        });

        static::updating(function (Model&MustRotatePassword&Authenticatable $model): void {
            if ($model->isDirty($model->getAuthPasswordName())) {
                $model->setAttribute($model->passwordRotationColumn(), $model->freshTimestamp());
            }
        });

        static::saved(function (Model&Authenticatable $model): void {
            $changed = $model->wasChanged($model->getAuthPasswordName()) || $model->wasRecentlyCreated;

            if (! $changed) {
                return;
            }

            if ((int) config('nova-password-rotation.history_count') > 0) {
                PasswordHistory::recordFor($model);
            }

            if (! $model->wasRecentlyCreated) {
                PasswordRotated::dispatch($model);
            }
        });
    }
}
