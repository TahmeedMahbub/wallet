<x-layouts::app title="Privacy Policy">
    <x-slot:topBar>
        <x-top-app-bar type="back" title="Privacy Policy" :back="url()->previous(route('login'))" />
    </x-slot:topBar>

    <div class="flex flex-col gap-10">
        <p class="text-12 leading-17 text-grey-700">Last updated: [date]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">What we store</h2>
        <p class="text-14 leading-20 text-grey-700">The accounts, entries, budgets, savings and dues you add, plus your name and email. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Who can see it</h2>
        <p class="text-14 leading-20 text-grey-700">Only you. Wallet staff do not read your financial data. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Backups and export</h2>
        <p class="text-14 leading-20 text-grey-700">Encrypted backups are kept for recovery. You can export or erase your data in Profile. [Client text]</p>

        <h2 class="mt-4 text-16 font-semibold leading-22 text-grey-900">Contact</h2>
        <p class="text-14 leading-20 text-grey-700">[Client contact email]</p>
    </div>
</x-layouts::app>
