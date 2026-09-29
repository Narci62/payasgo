<x-filament-widgets::widget>
    <div class="pg-panel">
        <div class="pg-panel-header">
            <div>
                <h2 class="pg-panel-title">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="size-5 text-warning-600 dark:text-warning-400" />
                    Points de vigilance
                </h2>
                <p class="pg-panel-subtitle mt-0.5">
                    Ce qui requiert une action de l&rsquo;équipe aujourd&rsquo;hui.
                </p>
            </div>
        </div>

        <div class="pg-alert-list">
            @foreach ($alerts as $alert)
                <div class="pg-alert">
                    <div class="pg-alert-main">
                        <span class="pg-alert-icon pg-alert-icon-{{ $alert['tone'] }}">
                            <x-filament::icon :icon="$alert['icon']" class="size-4" />
                        </span>

                        <div class="min-w-0">
                            <p class="pg-alert-title">{{ $alert['title'] }}</p>
                            <p class="pg-alert-detail">{{ $alert['detail'] }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-filament::badge :color="$alert['tone']" size="lg">
                            {{ $alert['count'] }}
                        </x-filament::badge>

                        @if ($alert['url'])
                            <x-filament::button
                                tag="a"
                                :href="$alert['url']"
                                color="gray"
                                size="xs"
                            >
                                Voir
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
