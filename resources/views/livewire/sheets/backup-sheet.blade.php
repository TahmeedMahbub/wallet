<div>
    @if ($open)
        <x-sheet title="Settings & backup">
            <x-info>Your data is saved automatically to your Wallet account and backed up every night. You can also keep your own copy.</x-info>

            @if ($preview)
                <p class="text-12 font-semibold leading-17 tracking-label text-grey-700">RESTORE THIS BACKUP?</p>

                <x-card class="gap-8 p-14">
                    <div class="flex justify-between text-14 leading-20"><span>Backup made</span><b class="font-semibold">{{ $preview['exported_at'] ? \Illuminate\Support\Carbon::parse($preview['exported_at'])->setTimezone(auth()->user()->timezone)->format('M j, Y, g:i A') : '—' }}</b></div>
                    <div class="flex justify-between text-14 leading-20"><span>Accounts</span><b class="font-semibold">{{ $preview['accounts'] }}</b></div>
                    <div class="flex justify-between text-14 leading-20"><span>Entries</span><b class="font-semibold">{{ $preview['entries'] }}</b></div>
                    <div class="flex justify-between text-14 leading-20"><span>Goals · dues</span><b class="font-semibold">{{ $preview['goals'] }} · {{ $preview['dues'] }}</b></div>
                    <div class="flex justify-between text-14 leading-20"><span>Wallet balance</span><b class="font-semibold">{{ \App\Support\Money::format($preview['wallet_balance']) }}</b></div>
                </x-card>

                <x-info variant="warn">This replaces everything you have now. Export your current data first if you want to keep a copy.</x-info>

                <x-field label="TYPE RESTORE TO CONFIRM" name="confirmText" placeholder="RESTORE" wire:model="confirmText" autocomplete="off" />
                <x-field label="YOUR PASSWORD" name="password" type="password" placeholder="Password" wire:model="password" autocomplete="current-password" />

                <x-button variant="danger" wire:click="restore" wire:loading.attr="disabled" wire:target="restore">Restore and replace my data</x-button>
                <x-button variant="secondary" wire:click="cancelRestore">Cancel</x-button>
            @else
                <p class="text-12 font-semibold leading-17 tracking-label text-grey-700">EXPORT</p>
                <p class="text-12 leading-17 text-grey-700">Download everything you have entered. JSON can be restored later; CSV opens in Excel or Sheets.</p>
                <div class="flex items-center gap-10">
                    <x-button variant="secondary" :href="route('export.json')" class="flex-1">Download JSON</x-button>
                    <x-button variant="secondary" :href="route('export.csv')" class="flex-1">Download CSV</x-button>
                </div>

                <p class="text-12 font-semibold leading-17 tracking-label text-grey-700">RESTORE</p>
                <p class="text-12 leading-17 text-grey-700">Restore replaces all current data with the file. You will see a preview first.</p>

                <label class="flex h-48 w-full cursor-pointer items-center justify-center rounded-14 border border-solid bg-grey-0 text-14 font-semibold leading-20 text-blue-700">
                    <span wire:loading.remove wire:target="file">Choose backup file</span>
                    <span wire:loading wire:target="file">Checking file…</span>
                    <input type="file" wire:model="file" accept=".json,application/json" class="sr-only">
                </label>

                @error('file')
                    <x-info variant="bad">{{ $message }}</x-info>
                @enderror

                <p class="text-12 font-semibold leading-17 tracking-label text-grey-700">START OVER</p>

                @if ($errors->has('password'))
                    <x-info variant="bad">{{ $errors->first('password') }}</x-info>
                @endif

                <x-button variant="danger" wire:click="erase" wire:loading.attr="disabled" wire:target="erase">{{ $confirmingErase ? 'Tap again to erase everything' : 'Erase all my data' }}</x-button>

                @if ($confirmingDelete)
                    <x-field label="YOUR PASSWORD" name="password" type="password" placeholder="Password" wire:model="password" autocomplete="current-password" />
                @endif

                <x-button variant="danger" wire:click="deleteAccount" wire:loading.attr="disabled" wire:target="deleteAccount">{{ $confirmingDelete ? 'Tap again to delete my account' : 'Delete my account' }}</x-button>
            @endif
        </x-sheet>
    @endif
</div>
