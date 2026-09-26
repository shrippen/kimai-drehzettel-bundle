<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\CategorySurcharge;
use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\StaggeredShoot;
use KimaiPlugin\DrehzettelBundle\Domain\Tiers;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

/**
 * One shooting day: work time, surcharge minutes, pay.
 *
 *   begin ──────────────── end    (each rounded on the clock, work rounding)
 *   gross = end - begin
 *   net   = gross - break          (break rule)
 *   work  = round(net)             (work rounding)
 *   tiers = split(work), each share rounded (surcharge rounding)
 *   night = overlap(begin..end, 22:00..06:00)
 */
class DayCalculator
{
    // TZ 5.6.3 sentence 3: "mehr als vier Stunden" on a Sunday/holiday pay its surcharge for the whole day.
    private const WHOLE_DAY_MINUTES = 4 * Units::MINUTES_PER_HOUR;

    public function __construct(private readonly PayCalculator $pay)
    {
    }

    public function calc(DayInput $day, Ruleset $rules, ?PayTerms $terms, int $dayNumber = 1): DayResult
    {
        // Rounded clock times, so the timesheet row adds up: begin/end/break shown give the work time.
        $day = $day->withTimes(
            $rules->workRounding->clock($day->begin, true),
            $rules->workRounding->clock($day->end, false),
        );
        $gross = $this->grossMinutes($day);
        $break = $this->deductedBreak($day, $rules, $gross);
        $work = $rules->workRounding->apply($gross - $break);

        // Travel time is paid like work time, without surcharges (TV FFS 12.1).
        $surcharged = $day->type === DayType::WORKDAY;
        $tiers = $surcharged ? Tiers::split($rules->dailyTiers, 0, $work, $rules->surchargeRounding) : [];
        $night = $surcharged ? $this->nightMinutes($day, $gross, $rules) : 0;
        [$category, $categoryShares, $waived] = $surcharged && $work > 0
            ? $this->categorySurcharges($day, $rules, $gross, $work, $dayNumber)
            : [null, [], null];
        $dayCount = $rules->countsAsDay($day->type) ? $this->dayCountShare($dayNumber, $work, $rules) : null;

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
            extraPayCents: $day->extraPayCents,
            shootingDayNumber: $day->shootingDayNumber,
            productionDay: $day->productionDay,
            categoryShares: $categoryShares,
            waivedCategory: $waived,
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

    /**
     * Saturday, Sunday and holiday surcharges (TZ 5.6).
     *
     * Within one calendar day, or with a category override: the category's
     * surcharge for the whole day. Past midnight each calendar day brings its
     * own category (TZ 5.6.1: Sunday work is work on the Sunday 0-24 h, also
     * when the working day began the day before):
     *
     *   Sunday or holiday part over 4 h -> its surcharge for the whole day (TZ 5.6.3 S. 3)
     *   any other part                  -> its percentage on the part's minutes ("zeitanteilig")
     *
     *   Fri 22:00-Sat 03:00  Saturday 25 % on 3 h
     *   Sat 18:00-Sun 03:00  Saturday 25 % on 6 h, Sunday 75 % on 3 h
     *   Sat 14:00-Sun 05:00  Saturday 25 % on 10 h, Sunday 75 % for the whole day
     *
     * Two whole-day surcharges (Sunday into a holiday) pay the higher one only.
     * A staggered shoot waives a Sunday or listed holiday part (StaggeredShoot).
     *
     * @return array{?CategorySurcharge, list<Share>, ?DayCategory} whole day, pro rata, waived
     */
    private function categorySurcharges(DayInput $day, Ruleset $rules, int $gross, int $work, int $dayNumber): array
    {
        $split = $day->nextCategory !== null;
        $whole = null;
        $shares = [];
        $waived = null;
        foreach ($this->calendarParts($day, $gross, $work) as [$category, $date, $minutes]) {
            if (StaggeredShoot::waives($category, $date, $dayNumber)) {
                $waived = $category;
                continue;
            }

            $surcharge = $rules->surchargeFor($category);
            if ($surcharge === null || $minutes === 0) {
                continue;
            }

            $restDay = $category === DayCategory::SUNDAY || $category === DayCategory::HOLIDAY;
            if (!$split || ($restDay && $minutes > self::WHOLE_DAY_MINUTES)) {
                $whole = $whole === null || $surcharge->basisPoints > $whole->basisPoints ? $surcharge : $whole;
                continue;
            }

            $shares[] = new Share($surcharge->basisPoints, $rules->surchargeRounding->apply($minutes));
        }

        return [$whole, $shares, $waived];
    }

    /**
     * Work minutes per calendar day. The break is spread like presence:
     * 18:00-03:00 with 45 min break = 495 min work, 330 before and 165 after midnight.
     *
     * @return list<array{DayCategory, \DateTimeImmutable, int}> category, date, work minutes
     */
    private function calendarParts(DayInput $day, int $gross, int $work): array
    {
        $date = $day->begin->setTime(0, 0);
        if ($day->nextCategory === null) {
            return [[$day->category, $date, $work]];
        }

        $next = $date->modify('+1 day');
        $after = min($gross, intdiv(max(0, $day->end->getTimestamp() - $next->getTimestamp()), Units::SECONDS_PER_MINUTE));
        $afterWork = intdiv(2 * $work * $after + $gross, 2 * $gross);

        return [[$day->category, $date, $work - $afterWork], [$day->nextCategory, $next, $afterWork]];
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
        array_push($hourly, ...$day->categoryShares);
        if ($category !== null && $category->basis === SurchargeBasis::DAY_RATE) {
            $dayRate = $category->basisPoints;
        }

        // Extra pay (Zusatzgage/Spesen) is a fixed amount on top: no surcharge, no rounding.
        $cents = $this->pay->dayCents($day->workMinutes, $hourly, $dayRate, $day->catering, $terms, $rules) + $day->extraPayCents;

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
            $day->extraPayCents,
            $day->shootingDayNumber,
            $day->productionDay,
            $day->categoryShares,
            $day->waivedCategory,
        );
    }
}
