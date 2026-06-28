<x-filament-widgets::widget>
    <x-filament::section>
        {{-- stats of client widgets --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Total Clients</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->totalClients }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Clients Actifs</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->activeClients }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Nouveaux Clients (30 jours)</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->totalClients }}</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
