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

    <script>
        if (localStorage.novaTheme === 'dark' || (!('novaTheme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
</head>
<body class="min-h-full text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900">
<div class="py-6 px-1 md:px-2 lg:px-6">
    <div class="mx-auto py-8 max-w-sm flex justify-center">
        <h1 class="text-3xl font-bold text-center text-gray-900 dark:text-white">{{ $novaName }}</h1>
    </div>

    <form
        method="POST"
        action="{{ route('nova-password-rotation.expired.update') }}"
        class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 w-[25rem] mx-auto"
    >
        @csrf

        <h2 class="text-2xl text-center font-normal mb-6">{{ trans('nova-password-rotation::messages.title') }}</h2>

        <p class="mb-6 text-center">{{ trans('nova-password-rotation::messages.intro') }}</p>

        @if ($errors->any())
            <div class="mb-6 space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="text-red-500">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (config('nova-password-rotation.require_current_password'))
            <div class="mb-6">
                <label class="block mb-2" for="current_password">{{ trans('nova-password-rotation::messages.current_password') }}</label>
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full form-control form-input form-control-bordered"
                >
            </div>
        @endif

        <div class="mb-6">
            <label class="block mb-2" for="password">{{ trans('nova-password-rotation::messages.new_password') }}</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
                class="w-full form-control form-input form-control-bordered"
            >
        </div>

        <div class="mb-6">
            <label class="block mb-2" for="password_confirmation">{{ trans('nova-password-rotation::messages.confirm_password') }}</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                class="w-full form-control form-input form-control-bordered"
            >
        </div>

        <button
            type="submit"
            class="w-full flex justify-center shadow rounded focus:outline-none focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-900 px-3 h-9 items-center text-sm font-bold"
        >
            {{ trans('nova-password-rotation::messages.submit') }}
        </button>
    </form>
</div>
</body>
</html>
