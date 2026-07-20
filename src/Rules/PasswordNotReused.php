<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Rules;

use BBSLab\NovaPasswordRotation\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordNotReused implements ValidationRule
{
    public function __construct(
        private Model $user,
        private ?int $count = null,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PasswordHistory::isReused($this->user, $value, $this->count)) {
            $fail('nova-password-rotation::validation.reused')->translate();
        }
    }
}
