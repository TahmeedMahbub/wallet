<x-layouts::app title="Home" nav="home">
    <x-slot:topBar>
        <x-top-app-bar type="home" :name="auth()->user()->name" />
    </x-slot:topBar>

    <div class="flex flex-1 flex-col items-center justify-center gap-12 text-center">
        <x-logo variant="color" :size="64" />
        <p class="text-16 font-semibold leading-22 text-grey-900">Wallet</p>
        <p class="text-13 leading-18 text-grey-700">Foundation shell. Screens arrive in later phases.</p>

        {{-- Temporary until the Profile screen provides Log out. --}}
        <form method="POST" action="{{ route('logout') }}" class="w-160">
            @csrf
            <x-button variant="secondary" type="submit">Log out</x-button>
        </form>
    </div>
</x-layouts::app>
