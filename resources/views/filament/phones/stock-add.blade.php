<x-filament::section
    heading="Ajouter du stock"
    description="Mettez à jour rapidement le stock du téléphone."
>

    <div class="grid gap-4 md:grid-cols-2">

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-sm text-gray-500">Marque</dt>
            <dd class="mt-1 font-semibold">
                {{ $phone->brand }}
            </dd>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-sm text-gray-500">Modèle</dt>
            <dd class="mt-1 font-semibold">
                {{ $phone->model }}
            </dd>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-sm text-gray-500">Stock actuel</dt>
            <dd class="mt-1 text-lg font-bold text-primary-600">
                {{ $phone->stock }}
            </dd>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-sm text-gray-500">Statut</dt>

            <dd class="mt-2">
                <x-filament::badge
                    :color="$phone->status === 'available' ? 'success' : 'danger'"
                >
                    {{ ucfirst($phone->status) }}
                </x-filament::badge>
            </dd>
        </div>

    </div>

    <form
        method="POST"
        action="{{ route('phones.stock-add', ['record' => $phone->id]) }}"
        class="mt-6"
    >
        @csrf

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">

            <div class="flex-1">
                <label
                    for="quantity"
                    class="mb-2 block text-sm font-medium"
                >
                    Quantité à ajouter
                </label>

                <x-filament::input
                    id="quantity"
                    name="quantity"
                    type="number"
                    min="1"
                    required
                />
            </div>

            <x-filament::button
                type="submit"
                icon="heroicon-m-plus"
                size="lg"
            >
                Ajouter
            </x-filament::button>

        </div>

    </form>

</x-filament::section>
