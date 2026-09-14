<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-danger-50 dark:bg-danger-950">
                    <x-heroicon-o-currency-dollar class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total pénalités</p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($this->getTotalPenalties(), 0, ',', ' ') }} XOF</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-warning-50 dark:bg-warning-950">
                    <x-heroicon-o-list-bullet class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nombre de pénalités</p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->getCountPenalties() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-info-50 dark:bg-info-950">
                    <x-heroicon-o-calculator class="w-6 h-6 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Moyenne par pénalité</p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($this->getAveragePenalty(), 0, ',', ' ') }} XOF</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-success-50 dark:bg-success-950">
                    <x-heroicon-o-calendar class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pénalités aujourd'hui</p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($this->getTodayPenalties(), 0, ',', ' ') }} XOF</p>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
