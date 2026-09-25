<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Share;
use KimaiPlugin\DrehzettelBundle\Domain\Tiers;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;

/**
 * One calendar week: days plus weekly overtime.
 *
 *   pool = max(0, regular - base) + pooled days
 *
 * regular: counted minutes of days 1-5 (daily overtime excluded)
 * base:    first weekly tier, TV FFS 50 h
 * pooled:  6th and 7th day when the rules give them no fixed surcharge
 *
 * The pool runs through the weekly tiers: TV FFS 25 % for 5 h, then 50 %.
 */
class WeekCalculator
{
    private const DATE_FORMAT = 'Y-m-d';
    private const WEEK_FORMAT = 'o-W';

    public function __construct(
        private readonly DayCalculator $days,
        private readonly PayCalculator $pay,
    ) {
    }

    /**
     * @param list<DayInput> $inputs entries of one ISO calendar week
     */
    public function calc(array $inputs, Ruleset $rules, ?PayTerms $terms): WeekResult
    {
        usort($inputs, static fn (DayInput $a, DayInput $b): int => $a->begin <=> $b->begin);
        $this->validate($inputs);

        $days = [];
        foreach ($inputs as $index => $input) {
            $number = $input->productionDay ?? $index + 1;
            $days[] = $this->days->calc($input, $rules, $terms, $number);
        }

        $pool = $this->poolMinutes($days, $rules);
        $shares = $this->weeklyShares($pool, $rules);
        $weeklyCents = $terms === null ? null : $this->weeklyCents($shares, $terms, $rules);

        return new WeekResult(
            days: $days,
            weeklyShares: $shares,
            weeklyPoolMinutes: $pool,
            workMinutes: array_sum(array_map(static fn (DayResult $d): int => $d->workMinutes, $days)),
            nightMinutes: array_sum(array_map(static fn (DayResult $d): int => $d->nightMinutes, $days)),
            weeklyCents: $weeklyCents,
            totalCents: $terms === null ? null : $weeklyCents + $this->dayCents($days),
        );
    }

    /**
     * @param list<DayInput> $inputs
     */
    private function validate(array $inputs): void
    {
        $seenDays = [];
        $weeks = [];
        foreach ($inputs as $input) {
            $seenDays[$input->begin->format(self::DATE_FORMAT)] = true;
            $weeks[$input->begin->format(self::WEEK_FORMAT)] = true;
        }

        if (count($seenDays) !== count($inputs)) {
            throw new \InvalidArgumentException('Two entries on one day. One shooting day is one entry.');
        }
        if (count($weeks) > 1) {
            throw new \InvalidArgumentException('Entries span more than one calendar week.');
        }
    }

    /**
     * @param list<DayResult> $days
     */
    private function poolMinutes(array $days, Ruleset $rules): int
    {
        if ($rules->weeklyTiers === []) {
            return 0;
        }

        $regular = 0;
        $pooled = 0;
        foreach ($days as $day) {
            if ($day->dayNumber <= Units::WEEK_WORKDAYS) {
                $regular += $day->countedMinutes;
                continue;
            }

            // 6th/7th day with a fixed surcharge is paid on its own, not pooled.
            if ($day->dayCountShare === null) {
                $pooled += $day->countedMinutes;
            }
        }

        return max(0, $regular - $rules->weeklyTiers[0]->afterMinutes) + $pooled;
    }

    /**
     * @return list<Share>
     */
    private function weeklyShares(int $pool, Ruleset $rules): array
    {
        if ($rules->weeklyTiers === []) {
            return [];
        }

        $base = $rules->weeklyTiers[0]->afterMinutes;

        return Tiers::split($rules->weeklyTiers, $base, $base + $pool, $rules->surchargeRounding);
    }

    /**
     * @param list<Share> $shares
     */
    private function weeklyCents(array $shares, PayTerms $terms, Ruleset $rules): int
    {
        return $this->pay->sharesCents($shares, $terms, $rules);
    }

    /**
     * @param list<DayResult> $days
     */
    private function dayCents(array $days): int
    {
        return array_sum(array_map(static fn (DayResult $d): int => $d->amountCents ?? 0, $days));
    }
}
