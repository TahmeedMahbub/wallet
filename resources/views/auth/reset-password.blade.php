<x-layouts::app title="New password">
    <x-slot:topBar>
        <x-top-app-bar type="back" title="New password" :back="route('login')" />
    </x-slot:topBar>

    @if (! $tokenIsValid)
        <x-info variant="bad">This reset link has expired or was already used.</x-info>

        <p class="text-14 leading-20 text-grey-700">Request a new one and use it within {{ config('auth.passwords.users.expire') }} minutes.</p>

        <x-button :href="route('password.request')">Send a new link</x-button>

        <p class="text-center text-13 leading-18"><a href="{{ route('login') }}" class="font-semibold text-blue-700">Back to log in</a></p>
    @else
        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-16">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <input type="hidden" name="email" value="{{ $request->query('email') }}">

            <p class="text-14 leading-20 text-grey-700">Choose a new password for {{ $request->query('email') }}.</p>

            @if ($errors->has('email'))
                <x-info variant="bad">{{ $errors->first('email') }}</x-info>
            @endif

            <x-field label="NEW PASSWORD" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" :autofocus="true" />
            <x-field label="CONFIRM PASSWORD" name="password_confirmation" type="password" placeholder="Type it again" autocomplete="new-password" />

            <x-button type="submit">Save password</x-button>
        </form>
    @endif
</x-layouts::app>
