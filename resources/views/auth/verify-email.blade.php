<x-layouts::app title="Verify your email">
    <x-auth-hero title="Verify your email" :padding-top="72">
        <x-slot:icon>
            <span class="flex size-64 items-center justify-center rounded-full bg-blue-50 text-blue-700"><x-icon name="info" class="size-28" /></span>
        </x-slot:icon>
        We sent a link to {{ auth()->user()->email }}. Open it to start using Wallet.
    </x-auth-hero>

    <x-button :href="route('home')">I have verified</x-button>

    <form
        method="POST"
        action="{{ route('verification.send') }}"
        x-data="{ wait: {{ session('status') === 'verification-link-sent' ? 60 : 0 }} }"
        x-init="if (wait > 0) { const t = setInterval(() => { wait--; if (wait <= 0) clearInterval(t) }, 1000) }"
    >
        @csrf
        <x-button variant="secondary" type="submit" x-bind:disabled="wait > 0">
            <span x-show="wait <= 0">Resend email</span>
            <span x-show="wait > 0" x-cloak x-text="'Resend email (' + wait + 's)'"></span>
        </x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center text-13 leading-18 text-grey-700">
        @csrf
        Wrong address? <button type="submit" class="font-semibold text-blue-700">Log out</button>
    </form>

    @if (session('status') === 'verification-link-sent')
        <x-toast>Verification email sent.</x-toast>
    @endif
</x-layouts::app>
