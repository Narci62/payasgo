<x-filament-widgets::widget>
    <div class="pg-kpi-grid">
        @foreach ($kpis as $kpi)
            <div class="pg-kpi" style="--pg-kpi-accent: var(--{{ $kpi['tone'] }}-500)">
                <span class="pg-kpi-icon">
                    <x-filament::icon :icon="$kpi['icon']" class="size-5" />
                </span>

                <div class="min-w-0">
                    <p class="pg-kpi-label">{{ $kpi['label'] }}</p>
                    <p class="pg-kpi-value mt-1.5">{{ $kpi['value'] }}</p>
                    <p class="pg-kpi-hint mt-1 truncate">{{ $kpi['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
