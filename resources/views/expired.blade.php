@php($novaName = \Laravel\Nova\Nova::name())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full font-sans antialiased">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trans('nova-password-rotation::messages.title') }} &middot; {{ $novaName }}</title>

    {{-- Reuse Nova's compiled stylesheet when it has been published so the page
         looks native; skip it gracefully otherwise (e.g. during tests). --}}
    @if (file_exists(public_path('vendor/nova/mix-manifest.json')))
        <link rel="stylesheet" href="{{ mix('app.css', 'vendor/nova') }}">
    @endif

    {{-- Override Nova's default primary palette with the configured brand colors,
         so primary-coloured controls (the submit button) match branding like the
         rest of Nova. Placed after app.css so the :root override wins. --}}
    <style>{!! \Laravel\Nova\Nova::brandColorsCSS() !!}</style>

    <script>
        if (localStorage.novaTheme === 'dark' || (!('novaTheme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>

    <style>
        /* This screen reuses Nova's compiled stylesheet, which only ships the
           utility classes Nova itself uses — so the reveal toggle's layout is
           defined here in plain CSS rather than relying on Tailwind utilities
           (e.g. pr-10) that are absent from that build. */
        .pr-field {
            position: relative;
        }

        /* Room for the toggle so a value never runs under it. */
        .pr-field input {
            padding-right: 2.75rem !important;
        }

        .pr-toggle {
            position: absolute;
            top: 0;
            bottom: 0;
            right: 0;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            color: #9ca3af;
            background: transparent;
            border: 0;
            cursor: pointer;
        }

        .pr-toggle:hover {
            color: #6b7280;
        }

        .dark .pr-toggle:hover {
            color: #d1d5db;
        }

        .pr-toggle svg {
            height: 1.25rem;
            width: 1.25rem;
        }

        /* Show only our own toggle: hide the browser-injected password controls
           (Chrome's autofill key on the autofilled current-password field, Edge's
           reveal eye) that would otherwise sit inside the field too. The
           strong-password generator button is intentionally left intact. */
        input::-webkit-credentials-auto-fill-button {
            visibility: hidden;
            display: none !important;
            pointer-events: none;
            margin: 0;
            width: 0;
            height: 0;
        }

        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }

        /* Keep a configured brand logo to a sensible size. */
        .pr-brand-logo svg,
        .pr-brand-logo img {
            max-height: 3rem;
            width: auto;
        }
    </style>
</head>
<body class="min-h-full text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900">
<div class="py-6 px-1 md:px-2 lg:px-6">
    <div class="mx-auto py-8 max-w-sm flex justify-center">
        {{-- Use the configured Nova brand logo when set (inline SVG or an image
             URL, mirroring Nova::logo() used on the login screen); otherwise fall
             back to the Nova name. --}}
        @php($novaLogo = \Laravel\Nova\Nova::logo())
        @if (! empty($novaLogo) && \Illuminate\Support\Str::contains($novaLogo, '<svg'))
            <div class="pr-brand-logo text-gray-900 dark:text-white">{!! $novaLogo !!}</div>
        @elseif (! empty($novaLogo))
            <img src="{{ $novaLogo }}" alt="{{ $novaName }}" class="pr-brand-logo">
        @else
            <h1 class="text-3xl font-bold text-center text-gray-900 dark:text-white">{{ $novaName }}</h1>
        @endif
    </div>

    @if (config('nova-password-rotation.expiry_action') === 'reset')
    <form
        method="POST"
        action="{{ route('nova-password-rotation.expired.reset') }}"
        class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 w-[25rem] mx-auto"
    >
        @csrf

        <h2 class="text-2xl text-center font-normal mb-6">{{ trans('nova-password-rotation::messages.title') }}</h2>

        <p class="mb-6 text-center">{{ trans('nova-password-rotation::messages.reset_intro') }}</p>

        <button
            type="submit"
            class="w-full flex justify-center shadow rounded focus:outline-none focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-900 px-3 h-9 items-center text-sm font-bold"
        >
            {{ trans('nova-password-rotation::messages.reset_submit') }}
        </button>
    </form>
    @else
    <form
        method="POST"
        action="{{ route('nova-password-rotation.expired.update') }}"
        class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 w-[25rem] mx-auto"
    >
        @csrf

        {{-- Password-manager anchor: a hidden username lets Chrome recognise this
             as a change-password form for a known account, so saved-password
             autofill and the strong-password generator target the right fields
             and do not overwrite the current-password field. See the Chromium
             "Create Amazing Password Forms" guidance. --}}
        @php($rotationUser = \Laravel\Nova\Nova::user(request()))
        <input
            type="text"
            name="username"
            autocomplete="username"
            value="{{ $rotationUser?->email ?? '' }}"
            tabindex="-1"
            aria-hidden="true"
            readonly
            style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"
        >

        {{-- Eye icons shared by every reveal toggle below (defined once). --}}
        <svg class="hidden" aria-hidden="true">
            <symbol id="pr-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </symbol>
            <symbol id="pr-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </symbol>
        </svg>

        <h2 class="text-2xl text-center font-normal mb-6">{{ trans('nova-password-rotation::messages.title') }}</h2>

        <p class="mb-6 text-center">{{ trans('nova-password-rotation::messages.intro') }}</p>

        @if ($errors->any())
            <div class="mb-6 space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="text-red-500">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (config('laravel-password-rotation.require_current_password'))
            <div class="mb-6">
                <label class="block mb-2" for="current_password">{{ trans('nova-password-rotation::messages.current_password') }}</label>
                <div class="pr-field">
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full form-control form-input form-control-bordered"
                    >
                    <button
                        type="button"
                        data-password-toggle="current_password"
                        aria-controls="current_password"
                        aria-pressed="false"
                        aria-label="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                        title="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                        class="pr-toggle"
                    >
                        <svg class="h-5 w-5" aria-hidden="true"><use href="#pr-eye"></use></svg>
                    </button>
                </div>
            </div>
        @endif

        <div class="mb-6">
            <label class="block mb-2" for="password">{{ trans('nova-password-rotation::messages.new_password') }}</label>
            <div class="pr-field">
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full form-control form-input form-control-bordered"
                >
                <button
                    type="button"
                    data-password-toggle="password"
                    aria-controls="password"
                    aria-pressed="false"
                    aria-label="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                    title="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                    class="pr-toggle"
                >
                    <svg class="h-5 w-5" aria-hidden="true"><use href="#pr-eye"></use></svg>
                </button>
            </div>
        </div>

        <div class="mb-6">
            <label class="block mb-2" for="password_confirmation">{{ trans('nova-password-rotation::messages.confirm_password') }}</label>
            <div class="pr-field">
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full form-control form-input form-control-bordered"
                >
                <button
                    type="button"
                    data-password-toggle="password_confirmation"
                    aria-controls="password_confirmation"
                    aria-pressed="false"
                    aria-label="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                    title="{{ trans('nova-password-rotation::messages.toggle_password') }}"
                    class="pr-toggle"
                >
                    <svg class="h-5 w-5" aria-hidden="true"><use href="#pr-eye"></use></svg>
                </button>
            </div>
        </div>

        <button
            type="submit"
            class="w-full flex justify-center shadow rounded focus:outline-none focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-900 px-3 h-9 items-center text-sm font-bold"
        >
            {{ trans('nova-password-rotation::messages.submit') }}
        </button>
    </form>
    @endif
</div>

<script>
    // Toggle a password field between hidden and clear text, swapping the eye icon.
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-password-toggle'));

            if (! input) {
                return;
            }

            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            button.querySelector('use').setAttribute('href', reveal ? '#pr-eye-off' : '#pr-eye');
        });
    });
</script>
</body>
</html>
