<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes an audit entry for every create / update / delete of the models it
 * is attached to (see AppServiceProvider). Records which fields changed;
 * values only for workflow fields, never for personal or secret ones.
 */
class AuditObserver
{
    /** Fields whose old -> new values are worth keeping. */
    private const TRACK_VALUES = ['status', 'role', 'is_verified', 'is_active', 'reminders_enabled', 'two_factor_enabled', 'min_rate_bps', 'max_rate_bps', 'processing_fee_bps', 'min_credit_score', 'max_loan_amount_paise', 'score', 'source'];

    private const IGNORE = ['updated_at', 'created_at', 'remember_token'];

    public function __construct(private AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record($this->action($model, 'created'), $model, $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $changed = array_diff(array_keys($model->getChanges()), self::IGNORE);
        if ($changed === []) {
            return;
        }

        $values = [];
        foreach (array_intersect($changed, self::TRACK_VALUES) as $field) {
            $values[$field] = ['from' => $this->plain($model->getOriginal($field)), 'to' => $this->plain($model->getAttribute($field))];
        }

        $this->audit->record($this->action($model, 'updated'), $model, array_filter([
            'fields' => array_values($changed),
            'changes' => $values,
        ]));
    }

    public function deleted(Model $model): void
    {
        $this->audit->record($this->action($model, 'deleted'), $model);
    }

    private function action(Model $model, string $event): string
    {
        return Str::snake(class_basename($model)).'.'.$event;
    }

    private function snapshot(Model $model): array
    {
        $values = [];
        foreach (self::TRACK_VALUES as $field) {
            if ($model->getAttribute($field) !== null) {
                $values[$field] = $this->plain($model->getAttribute($field));
            }
        }

        return $values;
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
