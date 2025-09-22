@can('createAnyResource')
    <div class="w-full ">
        <div class="subtitle">Destinations are used to segregate resources by network.</div>
        <form class="flex flex-col gap-4" wire:submit='submit'>
            <div class="flex gap-2">
                <x-forms.input id="name" label="Name" required />
                <x-forms.input id="network" label="Network" required />
            </div>
            <x-forms.select id="serverId" label="Select a server" required wire:change="generateName">
                <option disabled>Select a server</option>
                @foreach ($servers as $server)
                    <option value="{{ $server->id }}">{{ $server->name }}</option>
                @endforeach
            </x-forms.select>
            <x-forms.button type="submit">
                Continue
            </x-forms.button>
        </form>
    </div>
@else
    <div class="text-gray-500 p-4 text-center">
        <p>You don't have permission to create new destinations.</p>
        <p class="text-sm">Please contact your team administrator for access.</p>
    </div>
@endcan
