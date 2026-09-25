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

        // A negative stored break (bad import, old API) must never add work time.
        return max(0, min($break, $gross));
    }

    /**
     * Night windows are wall-clock [from, to) on the day before, of and after the begin,
     * measured in real time: a 07:30-00:00 shift overlaps 22:00-24:00 by 120 min, and
     * 22:00-06:00 lasts 9 h in the night clocks fall back (DST), 7 h when they spring forward.
     */
    private function nightMinutes(DayInput $day, int $gross, Ruleset $rules): int
    {
        $midnight = $day->begin->setTime(0, 0);
        $end = $day->begin->getTimestamp() + $gross * Units::SECONDS_PER_MINUTE;

        $seconds = 0;
        foreach ([-1, 0, 1] as $offset) {
            $windowDay = $midnight->modify("$offset day");
            $from = $this->wallClock($windowDay, $rules->nightFromMinute);
            $toDay = $rules->nightToMinute <= $rules->nightFromMinute ? $windowDay->modify('+1 day') : $windowDay;
            $to = $this->wallClock($toDay, $rules->nightToMinute);
            $seconds += max(0, min($end, $to) - max($day->begin->getTimestamp(), $from));
        }

        return $rules->surchargeRounding->apply(intdiv($seconds, Units::SECONDS_PER_MINUTE));
    }

    // Timestamp of a minute of the day on that date's wall clock (1320 -> 22:00).
    private function wallClock(\DateTimeImmutable $date, int $minuteOfDay): int
    {
        $hour = intdiv($minuteOfDay, Units::MINUTES_PER_HOUR);

        return $date->setTime($hour, $minuteOfDay % Units::MINUTES_PER_HOUR)->getTimestamp();
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
