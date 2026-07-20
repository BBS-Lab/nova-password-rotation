<?php

declare(strict_types=1);

namespace Workbench\App\Nova;

use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{
    /**
     * @var class-string<\Workbench\App\Models\User>
     */
    public static $model = \Workbench\App\Models\User::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array<int, string>
     */
    public static $search = ['id', 'name', 'email'];

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            // Laravel's framework rule (not Nova's PasswordValidationRules
            // trait, which is Nova 5 only) so the workbench boots on Nova 4 too.
            Password::make('Password')
                ->onlyOnForms()
                ->creationRules(['required', PasswordRule::defaults()])
                ->updateRules(['nullable', PasswordRule::defaults()]),

            // Updating the password above stamps this column via RotatesPassword.
            DateTime::make('Password changed at', 'password_changed_at')
                ->exceptOnForms()
                ->sortable(),
        ];
    }
}
