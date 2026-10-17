<?php

namespace App\Services;

use App\Enums\CreditScoreSource;
use App\Models\User;
use App\Support\ResolvedCreditScore;

class CreditScoreService
{
    /**
     * The score to base decisions on: the latest score on file. A bureau
     * score always wins; a score the customer types in is only recorded
     * (as self-reported) when no bureau score exists, and is never trusted
     * over one.
     */
    public function resolve(User $user, ?int $selfReported = null): ResolvedCreditScore
    {
        $latest = $user->latestCreditScore()->first();

        $canRecordSelfReport = $selfReported !== null
            && ($latest === null || ($latest->source === CreditScoreSource::UserInput && $latest->score !== $selfReported));

        if ($canRecordSelfReport) {
            $user->creditScores()->create([
                'score' => $selfReported,
                'source' => CreditScoreSource::UserInput,
                'fetched_at' => now(),
            ]);

            return new ResolvedCreditScore($selfReported, CreditScoreSource::UserInput);
        }

        return $latest
            ? new ResolvedCreditScore($latest->score, $latest->source)
            : new ResolvedCreditScore(null, null);
    }
}
