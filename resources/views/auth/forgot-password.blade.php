<x-layouts::app title="Forgot password">
    <x-slot:topBar>
        <x-top-app-bar type="back" title="{{ session('status') ? 'Check your email' : 'Forgot password' }}" :back="route('login')" />
    </x-slot:topBar>

    @if (session('status'))
        <x-auth-hero title="Link on its way" :padding-top="32">
            <x-slot:icon>
                <span class="flex size-64 items-center justify-center rounded-full bg-blue-50 text-blue-700"><x-icon name="check" class="size-28" /></span>
            </x-slot:icon>
            If {{ old('email', 'that address') }} has a Wallet account, a reset link is in the inbox. It expires in {{ config('auth.passwords.users.expire') }} minutes.
        </x-auth-hero>

        <x-button variant="secondary" :href="route('login')">Back to log in</x-button>

        <p class="text-center text-13 leading-18 text-grey-700">No email? Check spam, or <a href="{{ route('password.request') }}" class="font-semibold text-blue-700">send it again</a>.</p>
    @else
        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-16">
            @csrf

            <p class="text-14 leading-20 text-grey-700">Enter the email you used for Wallet. We will send you a link to choose a new password.</p>

            <x-field label="EMAIL" name="email" type="email" placeholder="you@example.com" autocomplete="email" :autofocus="true" />

            <x-button type="submit">Send reset link</x-button>

            <p class="text-center text-13 leading-18"><a href="{{ route('login') }}" class="font-semibold text-blue-700">Back to log in</a></p>
        </form>
    @endif
</x-layouts::app>
