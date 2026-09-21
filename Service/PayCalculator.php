<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

/**
 * Pay in cents. Integer math only: floats would drift on half-cent ties.
 *
 * hourly rate = weekly gage / 50 h, or daily gage / 10 h (TV FFS 5.7.1)
 * day rate    = weekly gage / 5, or the daily gage
 */
class PayCalculator
{
    /**
     * @param list<Share> $hourlyShares surcharges on the hourly rate
     */
    public function dayCents(
        int $workMinutes,
        array $hourlyShares,
        int $dayRateBasisPoints,
        Catering $catering,
        PayTerms $terms,
        Ruleset $rules,
    ): int {
        $paid = $this->paidMinutes($workMinutes, $terms, $rules);
        $amount = $this->weighted($paid, $hourlyShares, $terms, $rules);
        $amount += $this->dayRateCents($dayRateBasisPoints, $terms);

        if ($catering === Catering::YES) {
            $amount -= $terms->cateringDeductionCents;
        }

        return $amount;
    }

    /**
     * @param list<Share> $shares surcharge-only minutes, e.g. weekly overtime
     */
    public function sharesCents(array $shares, PayTerms $terms, Ruleset $rules): int
    {
        return $this->weighted(0, $shares, $terms, $rules);
    }

    // A daily gage pays at least a full day, a weekly gage pays worked time.
    private function paidMinutes(int $workMinutes, PayTerms $terms, Ruleset $rules): int
    {
        if ($terms->kind === PayKind::WEEKLY) {
            return $workMinutes;
        }

        return max($workMinutes, $rules->dailyGageHours * Units::MINUTES_PER_HOUR);
    }

    /**
     * @param list<Share> $shares
     */
    private function weighted(int $baseMinutes, array $shares, PayTerms $terms, Ruleset $rules): int
    {
        $hours = $terms->kind === PayKind::WEEKLY ? $rules->weeklyGageHours : $rules->dailyGageHours;
        $weighted = $baseMinutes * Units::BASIS_POINTS;
        foreach ($shares as $share) {
            $weighted += $share->minutes * $share->basisPoints;
        }

        $numerator = $terms->gageCents * $weighted;
        $denominator = $hours * Units::MINUTES_PER_HOUR * Units::BASIS_POINTS;

        return $this->halfUp($numerator, $denominator);
    }

    private function dayRateCents(int $basisPoints, PayTerms $terms): int
    {
        if ($basisPoints === 0) {
            return 0;
        }

        $days = $terms->kind === PayKind::WEEKLY ? Units::WEEK_WORKDAYS : 1;

        return $this->halfUp($terms->gageCents * $basisPoints, $days * Units::BASIS_POINTS);
    }

    private function halfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
