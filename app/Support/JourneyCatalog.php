<?php

namespace App\Support;

use App\Models\JourneyEvent;
use Illuminate\Support\Collection;

/**
 * The website journeys and their steps, in order. The website reports
 * "step reached" events using these keys (anything else is ignored), and
 * the admin funnel and customer progress are drawn from them.
 *
 * `steps` are the main path (the funnel); `side` are optional screens
 * along the way that are counted but are not funnel stages.
 */
final class JourneyCatalog
{
    public const JOURNEYS = [
        'signup' => [
            'label' => 'Sign-up',
            'steps' => [
                'home_visit' => 'Visited the home page',
                'opened' => 'Opened sign-up',
                'code_sent' => 'SMS code sent',
                'account_created' => 'Account created',
            ],
            'side' => [],
        ],
        'debt_management' => [
            'label' => 'Debt Management',
            'steps' => [
                'chooser' => 'Opened Debt Management',
                'consent_given' => 'Gave consent and picked a path',
            ],
            'side' => [],
        ],
        'credit_card' => [
            'label' => 'Credit card',
            'steps' => [
                'add_card' => 'Add your card',
                'analysis' => 'Where your money goes',
                'fixes' => 'Your way out',
                'switch_to_loan' => 'Switch to a loan',
                'offers' => 'Eligibility check',
                'offers_shown' => 'Offers shown',
                'applied' => 'Applied',
            ],
            'side' => [
                'plan' => 'Pay-more plan',
                'simulator' => 'Pay-more simulator',
                'hard_month' => 'Hard month options',
                'cant_pay' => 'Can’t pay this month',
            ],
        ],
        'loan' => [
            'label' => 'Loan',
            'steps' => [
                'details' => 'Loan details',
                'analysis' => 'Loan analysis',
                'fixes' => 'Ways to pay less',
                'eligibility' => 'Balance-transfer eligibility',
                'offers_shown' => 'Offers shown',
                'applied' => 'Applied',
            ],
            'side' => [],
        ],
        'multiple' => [
            'label' => 'Multiple debts',
            'steps' => [
                'debts' => 'Debt portfolio',
                'summary' => 'Debt overview',
                'eligibility' => 'Eligibility check',
                'offers_shown' => 'Plan and offers shown',
                'applied' => 'Applied',
            ],
            'side' => [],
        ],
        'life_events' => [
            'label' => 'Life Events',
            'steps' => [
                'hub' => 'Opened Life Events',
                'planner' => 'Planned an event',
                'advisor' => 'Asked to talk to an advisor',
            ],
            'side' => [],
        ],
    ];

    public static function isValid(string $journey, string $step): bool
    {
        $j = self::JOURNEYS[$journey] ?? null;

        return $j !== null && (isset($j['steps'][$step]) || isset($j['side'][$step]));
    }

    public static function journeyLabel(string $journey): string
    {
        return self::JOURNEYS[$journey]['label'] ?? $journey;
    }

    public static function stepLabel(string $journey, string $step): string
    {
        $j = self::JOURNEYS[$journey] ?? ['steps' => [], 'side' => []];

        return $j['steps'][$step] ?? $j['side'][$step] ?? $step;
    }

    /** "Credit card · Offers shown" (+ the event, e.g. "wedding", for Life Events). */
    public static function describe(?JourneyEvent $event): ?string
    {
        if ($event === null) {
            return null;
        }

        $text = self::journeyLabel($event->journey).' · '.self::stepLabel($event->journey, $event->step);

        return $event->detail ? $text.' ('.str_replace(['_', '-'], ' ', $event->detail).')' : $text;
    }

    /** 1-based position on the main path; 0 for side screens and unknown steps. */
    public static function rank(string $journey, string $step): int
    {
        $position = array_search($step, array_keys(self::JOURNEYS[$journey]['steps'] ?? []), true);

        return $position === false ? 0 : $position + 1;
    }

    /** The step's name, plus its detail (e.g. "Planned an event (wedding)"). */
    public static function stepText(?JourneyEvent $event): ?string
    {
        if ($event === null) {
            return null;
        }
        $label = self::stepLabel($event->journey, $event->step);

        return $event->detail ? $label.' ('.str_replace(['_', '-'], ' ', $event->detail).')' : $label;
    }

    /**
     * Main-path steps as filter options: "credit_card.offers_shown" => "Credit card · Offers shown".
     *
     * @return array<string, string>
     */
    public static function mainStepOptions(): array
    {
        $options = [];
        foreach (self::JOURNEYS as $key => $journey) {
            foreach ($journey['steps'] as $step => $label) {
                $options["{$key}.{$step}"] = "{$journey['label']} · {$label}";
            }
        }

        return $options;
    }

    /**
     * Main-path steps that come after $step, e.g. after "offers_shown": ["applied"].
     *
     * @return list<string>
     */
    public static function stepsAfter(string $journey, string $step): array
    {
        $steps = array_keys(self::JOURNEYS[$journey]['steps'] ?? []);
        $position = array_search($step, $steps, true);

        return $position === false ? [] : array_slice($steps, $position + 1);
    }

    /**
     * One line per journey the customer has used: the furthest step on the
     * main path, whether they applied, and when they were last active.
     *
     * @param  Collection<int, JourneyEvent>  $events
     * @return list<string>
     */
    public static function progressLines(Collection $events): array
    {
        $lines = [];
        foreach (self::JOURNEYS as $key => $journey) {
            $mine = $events->where('journey', $key);
            if ($mine->isEmpty() || $key === 'signup') {
                continue;
            }

            $furthest = $mine->sortByDesc(fn (JourneyEvent $e) => self::rank($key, $e->step))->first();
            $total = count($journey['steps']);
            $rank = self::rank($key, $furthest->step);
            $last = $mine->max('created_at');
            $status = $furthest->step === 'applied'
                ? 'applied'
                : ($rank > 0 ? "stopped at step {$rank} of {$total}" : 'browsing');

            $lines[] = sprintf(
                '%s: %s — %s · last active %s',
                $journey['label'],
                self::stepLabel($key, $furthest->step),
                $status,
                $last?->diffForHumans() ?? '—',
            );
        }

        return $lines;
    }
}
