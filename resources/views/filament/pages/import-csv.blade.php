<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                Import CSV
            </x-filament::button>
        </div>
    </form>

    @if ($result)
        <div class="mt-6 space-y-4">
            <x-filament::section>
                <x-slot name="heading">Import Results</x-slot>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="rounded-lg bg-green-50 dark:bg-green-950 p-4">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ $result['imported'] }}
                        </div>
                        <div class="text-sm text-green-700 dark:text-green-300">Imported</div>
                    </div>

                    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950 p-4">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                            {{ $result['skipped'] }}
                        </div>
                        <div class="text-sm text-yellow-700 dark:text-yellow-300">Skipped</div>
                    </div>

                    <div class="rounded-lg bg-red-50 dark:bg-red-950 p-4">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ count($result['errors']) }}
                        </div>
                        <div class="text-sm text-red-700 dark:text-red-300">Errors</div>
                    </div>
                </div>

                @if (count($result['errors']) > 0)
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Row Errors:</p>
                        <ul class="space-y-1">
                            @foreach ($result['errors'] as $error)
                                <li class="text-sm text-red-600 dark:text-red-400 font-mono">
                                    {{ $error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
