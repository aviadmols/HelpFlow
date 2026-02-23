<x-filament-panels::page>
    <x-filament-panels::form id="openrouter-settings-form" wire:submit="save">
        {{ $this->form }}
        <div class="mt-6">
            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
