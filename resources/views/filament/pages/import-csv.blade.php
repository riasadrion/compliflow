<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Upload CSV File</x-slot>
        <x-slot name="description">Upload a CSV where each row represents a service log event with its associated client and authorization. Clients and authorizations are auto-created or matched by ID; service logs are always added fresh.</x-slot>

        <form wire:submit="import" x-data="{
            hasFile() {
                const f = this.$wire.data?.csv_file;
                if (! f) return false;
                if (typeof f === 'string') return f.length > 0;
                if (Array.isArray(f)) return f.length > 0;
                return Object.keys(f).length > 0;
            }
        }">
            {{ $this->form }}

            <br>

            <x-filament::button
                type="submit"
                icon="heroicon-o-arrow-up-tray"
                size="lg"
                x-bind:disabled="! hasFile()"
                x-bind:class="! hasFile() ? 'opacity-50 cursor-not-allowed' : ''"
            >
                Import CSV
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($result)
        <style>
            .csv-results-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
            .csv-stat { border-radius: 0.75rem; padding: 1.25rem; text-align: center; border: 1px solid; }
            .csv-stat-num { font-size: 2.25rem; font-weight: 700; line-height: 1; }
            .csv-stat-label { font-size: 0.875rem; font-weight: 600; margin-top: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }

            .csv-stat-green { background-color: rgb(240 253 244); border-color: rgb(187 247 208); }
            .csv-stat-green .csv-stat-num { color: rgb(22 163 74); }
            .csv-stat-green .csv-stat-label { color: rgb(21 128 61); }

            .csv-stat-yellow { background-color: rgb(254 252 232); border-color: rgb(254 240 138); }
            .csv-stat-yellow .csv-stat-num { color: rgb(202 138 4); }
            .csv-stat-yellow .csv-stat-label { color: rgb(161 98 7); }

            .csv-stat-red { background-color: rgb(254 242 242); border-color: rgb(254 202 202); }
            .csv-stat-red .csv-stat-num { color: rgb(220 38 38); }
            .csv-stat-red .csv-stat-label { color: rgb(185 28 28); }

            .csv-breakdown { margin-top: 1.25rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
            .csv-break-card { border-radius: 0.5rem; background-color: rgb(249 250 251); padding: 0.75rem 1rem; border: 1px solid rgb(229 231 235); }
            .csv-break-label { font-size: 0.75rem; font-weight: 600; color: rgb(107 114 128); text-transform: uppercase; letter-spacing: 0.05em; }
            .csv-break-body { font-size: 0.9375rem; color: rgb(17 24 39); margin-top: 0.25rem; }
            .csv-break-new { font-weight: 600; color: rgb(22 163 74); }
            .csv-break-matched { font-weight: 600; color: rgb(75 85 99); }
            .csv-break-sep { color: rgb(156 163 175); margin: 0 0.375rem; }

            .csv-errors-wrap { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgb(229 231 235); }
            .csv-errors-title { font-size: 0.875rem; font-weight: 600; color: rgb(55 65 81); margin-bottom: 0.75rem; }
            .csv-errors-list { display: flex; flex-direction: column; gap: 0.375rem; }
            .csv-error-item { font-size: 0.8125rem; color: rgb(185 28 28); font-family: ui-monospace, monospace; background-color: rgb(254 242 242); padding: 0.5rem 0.75rem; border-radius: 0.375rem; border-left: 3px solid rgb(220 38 38); }

            .dark .csv-stat-green { background-color: rgb(5 46 22); border-color: rgb(22 101 52); }
            .dark .csv-stat-green .csv-stat-num { color: rgb(134 239 172); }
            .dark .csv-stat-green .csv-stat-label { color: rgb(187 247 208); }

            .dark .csv-stat-yellow { background-color: rgb(66 32 6); border-color: rgb(113 63 18); }
            .dark .csv-stat-yellow .csv-stat-num { color: rgb(253 224 71); }
            .dark .csv-stat-yellow .csv-stat-label { color: rgb(254 240 138); }

            .dark .csv-stat-red { background-color: rgb(69 10 10); border-color: rgb(153 27 27); }
            .dark .csv-stat-red .csv-stat-num { color: rgb(252 165 165); }
            .dark .csv-stat-red .csv-stat-label { color: rgb(254 202 202); }

            .dark .csv-break-card { background-color: rgb(31 41 55); border-color: rgb(55 65 81); }
            .dark .csv-break-label { color: rgb(156 163 175); }
            .dark .csv-break-body { color: rgb(243 244 246); }
            .dark .csv-break-new { color: rgb(134 239 172); }
            .dark .csv-break-matched { color: rgb(209 213 219); }
            .dark .csv-break-sep { color: rgb(107 114 128); }

            .dark .csv-errors-wrap { border-top-color: rgb(55 65 81); }
            .dark .csv-errors-title { color: rgb(209 213 219); }
            .dark .csv-error-item { color: rgb(252 165 165); background-color: rgb(69 10 10); border-left-color: rgb(248 113 113); }
        </style>

        <div style="margin-top: 1.5rem;">
            <x-filament::section>
                <x-slot name="heading">Import Results</x-slot>

                <div class="csv-results-grid">
                    <div class="csv-stat csv-stat-green">
                        <div class="csv-stat-num">{{ $result['imported'] }}</div>
                        <div class="csv-stat-label">Imported</div>
                    </div>

                    <div class="csv-stat csv-stat-yellow">
                        <div class="csv-stat-num">{{ $result['skipped'] }}</div>
                        <div class="csv-stat-label">Skipped</div>
                    </div>

                    <div class="csv-stat csv-stat-red">
                        <div class="csv-stat-num">{{ count($result['errors']) }}</div>
                        <div class="csv-stat-label">Errors</div>
                    </div>
                </div>

                <div class="csv-breakdown">
                    <div class="csv-break-card">
                        <div class="csv-break-label">Clients</div>
                        <div class="csv-break-body">
                            <span class="csv-break-new">{{ $result['clients_created'] ?? 0 }}</span> new
                            <span class="csv-break-sep">·</span>
                            <span class="csv-break-matched">{{ $result['clients_matched'] ?? 0 }}</span> matched
                        </div>
                    </div>

                    <div class="csv-break-card">
                        <div class="csv-break-label">Authorizations</div>
                        <div class="csv-break-body">
                            <span class="csv-break-new">{{ $result['auths_created'] ?? 0 }}</span> new
                            <span class="csv-break-sep">·</span>
                            <span class="csv-break-matched">{{ $result['auths_matched'] ?? 0 }}</span> matched
                        </div>
                    </div>

                    <div class="csv-break-card">
                        <div class="csv-break-label">Service Logs</div>
                        <div class="csv-break-body">
                            <span class="csv-break-new">{{ $result['logs_created'] ?? 0 }}</span> new
                        </div>
                    </div>
                </div>

                @if (count($result['errors']) > 0)
                    <div class="csv-errors-wrap">
                        <p class="csv-errors-title">Row Errors:</p>
                        <ul class="csv-errors-list">
                            @foreach ($result['errors'] as $error)
                                <li class="csv-error-item">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
