<x-filament-panels::page>
    {{-- Inline styles: a Filament panel without a custom theme doesn't compile arbitrary Tailwind classes --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
        <p style="font-size: 0.875rem; opacity: 0.75; max-width: 46rem; margin: 0;">
            How many different people reached each step of the website journeys, and where they drop off.
            To see who stopped where, use the <strong>Last step</strong> column and filter on Customers.
        </p>
        <div style="width: 13rem;">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="range" aria-label="Period">
                    @foreach (\App\Filament\Pages\JourneyFunnel::RANGES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @foreach ($this->funnels() as $funnel)
        <x-filament::section :heading="$funnel['label']">
            @if ($funnel['start'] === 0 && collect($funnel['steps'])->sum('people') === 0)
                <p style="font-size: 0.875rem; opacity: 0.7; margin: 0;">No activity in this period.</p>
            @else
                @if ($funnel['biggestDrop'])
                    <p style="font-size: 0.8125rem; margin: 0 0 14px; padding: 8px 12px; border-radius: 8px; background: rgba(244, 63, 94, 0.08); color: #be123c;">
                        Biggest drop: <strong>{{ $funnel['biggestDrop']['from'] }}</strong> → <strong>{{ $funnel['biggestDrop']['to'] }}</strong>
                        ({{ $funnel['biggestDrop']['drop'] }}% don’t continue)
                    </p>
                @endif

                <div style="display: grid; gap: 12px;">
                    @foreach ($funnel['steps'] as $step)
                        <div>
                            <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 0.875rem;">
                                <span>{{ $loop->iteration }}. {{ $step['label'] }}</span>
                                <span style="white-space: nowrap;">
                                    <strong>{{ number_format($step['people']) }}</strong>
                                    {{ $step['people'] === 1 ? 'person' : 'people' }} · {{ $step['pct'] }}%
                                    @if ($step['drop'] !== null && $step['drop'] > 0)
                                        <span style="color: #e11d48;">(−{{ $step['drop'] }}%)</span>
                                    @endif
                                </span>
                            </div>
                            <div style="height: 8px; margin-top: 5px; border-radius: 9999px; background: rgba(127, 127, 127, 0.15); overflow: hidden;">
                                <div style="height: 100%; width: {{ $step['pct'] }}%; border-radius: 9999px; background: #3b82f6;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (count($funnel['side']))
                    <p style="font-size: 0.8125rem; opacity: 0.75; margin: 16px 0 0;">
                        Also visited:
                        @foreach ($funnel['side'] as $side)
                            {{ $side['label'] }} ({{ number_format($side['people']) }}){{ $loop->last ? '' : ' · ' }}
                        @endforeach
                    </p>
                @endif
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
