@php
    $emailError = $errors->first('email');
    $isThrottled = str_starts_with($emailError, 'Too many login attempts');
    $loginFailed = $emailError === __('auth.failed') || $isThrottled;
@endphp

<x-layouts::app title="Log in">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-16">
        @csrf

        <x-auth-hero title="Welcome back" :padding-top="64">Log in to see your money.</x-auth-hero>

        @if (session('status'))
            <x-info variant="info">{{ session('status') }}</x-info>
        @endif

        @if ($loginFailed)
            <x-info variant="bad">{{ $isThrottled ? $emailError : 'Email, username or password is not correct. Please try again.' }}</x-info>
        @endif

        <x-field label="EMAIL OR USERNAME" name="email" type="text" placeholder="Email or username" autocomplete="username" autocapitalize="none" :invalid="$loginFailed" :autofocus="! $loginFailed" />
        <x-field label="PASSWORD" name="password" type="password" placeholder="Your password" autocomplete="current-password" :invalid="$loginFailed" />

        <div class="flex justify-end">
            <a href="{{ route('password.request') }}" class="text-13 font-semibold leading-18 text-blue-700">Forgot password?</a>
        </div>

        <x-checkbox name="remember" :checked="true">Keep me logged in on this device</x-checkbox>

        <x-button type="submit">Log in</x-button>

        <p class="text-center text-13 leading-18 text-grey-700">New to Wallet? <a href="{{ route('register') }}" class="font-semibold text-blue-700">Create an account</a></p>
    </form>
</x-layouts::app>
