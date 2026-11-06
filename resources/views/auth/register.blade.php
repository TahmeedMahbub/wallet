<x-layouts::app title="Create account">
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-16">
        @csrf

        <x-auth-hero title="Create your Wallet" :logo="56" :padding-top="40">Track income, spending, savings and dues in one place.</x-auth-hero>

        <x-field label="YOUR NAME" name="name" placeholder="Name shown on Home" autocomplete="name" :autofocus="true" />
        <x-field label="USERNAME" name="username" placeholder="Used to log in" autocomplete="username" autocapitalize="none" />
        <x-field label="EMAIL" name="email" type="email" placeholder="you@example.com" autocomplete="email" />
        <x-field label="PASSWORD" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" />
        <x-field label="CONFIRM PASSWORD" name="password_confirmation" type="password" placeholder="Type it again" autocomplete="new-password" />

        <x-checkbox name="terms">I agree to the <a href="{{ route('terms') }}" class="font-semibold text-blue-700">Terms</a> and <a href="{{ route('privacy') }}" class="font-semibold text-blue-700">Privacy Policy</a></x-checkbox>

        <x-button type="submit">Create account</x-button>

        <p class="text-center text-13 leading-18 text-grey-700">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-700">Log in</a></p>
    </form>
</x-layouts::app>
