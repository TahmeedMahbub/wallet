<x-layouts::app title="Terms of Service">
    <x-slot:topBar>
        <x-top-app-bar type="back" title="Terms of Service" :back="url()->previous(route('login'))" />
    </x-slot:topBar>

    <div class="flex flex-col gap-10">
        <p class="text-12 leading-17 text-grey-700">Last updated: [date]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Using Wallet</h2>
        <p class="text-14 leading-20 text-grey-700">Wallet is a personal money tracker. You are responsible for the entries you add. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Your account</h2>
        <p class="text-14 leading-20 text-grey-700">Keep your password private. You can export or delete your data at any time. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Limits</h2>
        <p class="text-14 leading-20 text-grey-700">Wallet records what you enter and does not give financial advice. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Contact</h2>
        <p class="text-14 leading-20 text-grey-700">[Client contact email]</p>
    </div>
</x-layouts::app>
