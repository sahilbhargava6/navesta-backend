<?php

namespace App\Filament\Pages;

use App\Models\JourneyEvent;
use App\Support\JourneyCatalog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Where people drop off in each website journey: for every step, how many
 * different people reached it in the chosen period, its share of the first
 * step, and the drop from the step before. Customer-level progress is on
 * Customers (the "Last step" column, its filter and each customer's page).
 */
class JourneyFunnel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Journey funnel';

    protected static ?string $title = 'Journey funnel';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.journey-funnel';

    /** Days back from today. */
    public string $range = '30';

    public const RANGES = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months'];

    /**
     * @return list<array{key: string, label: string, start: int, steps: list<array{label: string, people: int, pct: int, drop: int|null}>, side: list<array{label: string, people: int}>, biggestDrop: array{from: string, to: string, drop: int}|null}>
     */
    public function funnels(): array
    {
        $days = array_key_exists($this->range, self::RANGES) ? (int) $this->range : 30;

        // Different people per step: the account when signed in, else the browser id
        $people = JourneyEvent::query()
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('journey, step, count(distinct coalesce(cast(user_id as varchar), cast(visitor_id as varchar))) as people')
            ->groupBy('journey', 'step')
            ->toBase() // plain rows: these are counts, not events
            ->get()
            ->mapWithKeys(fn (object $row) => ["{$row->journey}.{$row->step}" => (int) $row->people]);

        $funnels = [];
        foreach (JourneyCatalog::JOURNEYS as $key => $journey) {
            $start = $people["{$key}.".array_key_first($journey['steps'])] ?? 0;
            $steps = [];
            $previous = null;
            $biggest = null;

            foreach ($journey['steps'] as $step => $label) {
                $count = $people["{$key}.{$step}"] ?? 0;
                $drop = $previous !== null && $previous['people'] > 0
                    ? max(0, (int) round(100 - $count * 100 / $previous['people']))
                    : null;
                if ($drop !== null && ($biggest === null || $drop > $biggest['drop'])) {
                    $biggest = ['from' => $previous['label'], 'to' => $label, 'drop' => $drop];
                }
                $steps[] = $previous = [
                    'label' => $label,
                    'people' => $count,
                    'pct' => $start > 0 ? min(100, (int) round($count * 100 / $start)) : 0,
                    'drop' => $drop,
                ];
            }

            $funnels[] = [
                'key' => $key,
                'label' => $journey['label'],
                'start' => $start,
                'steps' => $steps,
                'side' => array_map(
                    fn (string $step, string $label) => ['label' => $label, 'people' => $people["{$key}.{$step}"] ?? 0],
                    array_keys($journey['side']),
                    $journey['side'],
                ),
                'biggestDrop' => $biggest && $biggest['drop'] > 0 ? $biggest : null,
            ];
        }

        return $funnels;
    }
}
