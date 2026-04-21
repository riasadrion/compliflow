<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Upload CSV File</x-slot>
        <x-slot name="description">Import clients, authorizations, and service logs in bulk.</x-slot>

        <form wire:submit="import">
            {{ $this->form }}

            <br>

            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" size="lg">
                Import CSV
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($result)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Import Results</x-slot>

            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="rounded-lg bg-green-50 dark:bg-green-950 p-4">
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                        {{ $result['imported'] }}
                    </div>
                    <div class="text-sm font-medium text-green-700 dark:text-green-300 mt-1">Imported</div>
                </div>

                <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950 p-4">
                    <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ $result['skipped'] }}
                    </div>
                    <div class="text-sm font-medium text-yellow-700 dark:text-yellow-300 mt-1">Skipped</div>
                </div>

                <div class="rounded-lg bg-red-50 dark:bg-red-950 p-4">
                    <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                        {{ count($result['errors']) }}
                    </div>
                    <div class="text-sm font-medium text-red-700 dark:text-red-300 mt-1">Errors</div>
                </div>
            </div>

            @if (count($result['errors']) > 0)
                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Row Errors:</p>
                    <ul class="space-y-1">
                        @foreach ($result['errors'] as $error)
                            <li class="text-sm text-red-600 dark:text-red-400 font-mono bg-red-50 dark:bg-red-950 px-3 py-1 rounded">
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
