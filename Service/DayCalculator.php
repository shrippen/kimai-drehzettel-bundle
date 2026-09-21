<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\Tiers;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

/**
 * One shooting day: work time, surcharge minutes, pay.
 *
 *   begin ──────────────── end
 *   gross = end - begin
 *   net   = gross - break          (break rule)
 *   work  = round(net)             (work rounding)
 *   tiers = split(work), each share rounded (surcharge rounding)
 *   night = overlap(begin..end, 22:00..06:00)
 */
class DayCalculator
{
    public function __construct(private readonly PayCalculator $pay)
    {
    }

    public function calc(DayInput $day, Ruleset $rules, ?PayTerms $terms, int $dayNumber = 1): DayResult
    {
        $gross = $this->grossMinutes($day);
        $break = $this->deductedBreak($day, $rules, $gross);
        $work = $rules->workRounding->apply($gross - $break);

        // Travel time is paid like work time, without surcharges (TV FFS 12.1).
        $surcharged = $day->type === DayType::WORKDAY;
        $tiers = $surcharged ? Tiers::split($rules->dailyTiers, 0, $work, $rules->surchargeRounding) : [];
        $night = $surcharged ? $this->nightMinutes($day, $gross, $rules) : 0;
        $category = $surcharged && $work > 0 ? $rules->surchargeFor($day->category) : null;
        $dayCount = $surcharged ? $this->dayCountShare($dayNumber, $work, $rules) : null;

        $result = new DayResult(
            begin: $day->begin,
            end: $day->end,
            grossMinutes: $gross,
            breakMinutes: $break,
            workMinutes: $work,
            dailyShares: $tiers,
            nightMinutes: $night,
            categorySurcharge: $category,
            dayCountShare: $dayCount,
            countedMinutes: $surcharged ? min($work, $this->weeklyCountLimit($rules)) : $work,
            dayNumber: $dayNumber,
            catering: $day->catering,
            amountCents: null,
            dayType: $day->type,
            category: $day->category,
            note: $day->note,
            underMinutes: $surcharged && $work > 0 ? max(0, $rules->minDayMinutes - $work) : 0,
        );

        if ($terms === null) {
            return $result;
        }

        return $this->withAmount($result, $rules, $terms);
    }

    private function grossMinutes(DayInput $day): int
    {
        $seconds = $day->end->getTimestamp() - $day->begin->getTimestamp();

        return max(0, intdiv($seconds, 60));
    }

    private function deductedBreak(DayInput $day, Ruleset $rules, int $gross): int
    {
        $break = $day->breakMinutes ?? $rules->defaultBreakMinutes;
        if ($rules->breakRule === BreakRule::EXCESS_COUNTS_AS_WORK) {
            $break = min($break, $rules->freeBreakMinutes);
        }

        return min($break, $gross);
    }

    /**
     * Night windows sit at k * 24 h + [from, to). Shift minutes are counted
     * from the begin day's midnight, so a 07:30-00:00 shift spans 450..1440
     * and overlaps 22:00-24:00 (1320..1440) by 120 min.
     */
    private function nightMinutes(DayInput $day, int $gross, Ruleset $rules): int
    {
        $start = (int) $day->begin->format('G') * Units::MINUTES_PER_HOUR + (int) $day->begin->format('i');
        $end = $start + $gross;
        $to = $rules->nightToMinute <= $rules->nightFromMinute
            ? $rules->nightToMinute + Units::MINUTES_PER_DAY
            : $rules->nightToMinute;

        $sum = 0;
        foreach ([-1, 0, 1] as $offset) {
            $shift = $offset * Units::MINUTES_PER_DAY;
            $sum += max(0, min($end, $shift + $to) - max($start, $shift + $rules->nightFromMinute));
        }

        return $rules->surchargeRounding->apply($sum);
    }

    private function dayCountShare(int $dayNumber, int $work, Ruleset $rules): ?Share
    {
        $basisPoints = match (true) {
            $dayNumber >= Units::SEVENTH_DAY => $rules->seventhDayBasisPoints,
            $dayNumber === Units::SIXTH_DAY => $rules->sixthDayBasisPoints,
            default => null,
        };

        return $basisPoints === null ? null : new Share($basisPoints, $work);
    }

    // Daily overtime does not count toward the weekly base (TV FFS 5.4.3.2).
    private function weeklyCountLimit(Ruleset $rules): int
    {
        return $rules->dailyTiers[0]->afterMinutes ?? PHP_INT_MAX;
    }

    private function withAmount(DayResult $day, Ruleset $rules, PayTerms $terms): DayResult
    {
        $hourly = $day->dailyShares;
        $hourly[] = new Share($rules->nightBasisPoints, $day->nightMinutes);
        if ($day->dayCountShare !== null) {
            $hourly[] = $day->dayCountShare;
        }

        $dayRate = 0;
        $category = $day->categorySurcharge;
        if ($category !== null && $category->basis === SurchargeBasis::HOURLY) {
            $hourly[] = new Share($category->basisPoints, $day->workMinutes);
        }
        if ($category !== null && $category->basis === SurchargeBasis::DAY_RATE) {
            $dayRate = $category->basisPoints;
        }

        $cents = $this->pay->dayCents($day->workMinutes, $hourly, $dayRate, $day->catering, $terms, $rules);

        return new DayResult(
            $day->begin,
            $day->end,
            $day->grossMinutes,
            $day->breakMinutes,
            $day->workMinutes,
            $day->dailyShares,
            $day->nightMinutes,
            $day->categorySurcharge,
            $day->dayCountShare,
            $day->countedMinutes,
            $day->dayNumber,
            $day->catering,
            $cents,
            $day->dayType,
            $day->category,
            $day->note,
            $day->underMinutes,
        );
    }
}
