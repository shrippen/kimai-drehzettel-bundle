<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\ComplianceWarning;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\Units;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;
use KimaiPlugin\DrehzettelBundle\Enum\ComplianceIssue;

/**
 * Flags TV FFS working-time and rest-time limits. Advisory only: it never
 * changes a calculation, it only points at days worth checking by hand.
 *
 * TZ 5.2.5: 12 h/day, 60 h/week. TZ 5.9.1: 11 h rest between two shooting
 * days, or 11.5 h once the earlier day reached a begun 12th hour.
 *
 * Rest time also covers the gap across a week boundary (last day of the
 * previous week to the first day of this one) when the caller passes that
 * day in as $previousDay (see FilmWeekService::lastDayBefore()).
 */
class ComplianceChecker
{
    private const DAILY_MAX_MINUTES = 12 * Units::MINUTES_PER_HOUR;
    private const WEEKLY_MAX_MINUTES = 60 * Units::MINUTES_PER_HOUR;
    private const REST_MINUTES = 11 * Units::MINUTES_PER_HOUR;
    private const REST_EXTENDED_MINUTES = 11 * Units::MINUTES_PER_HOUR + 30;
    // A "begun 12th hour" means gross time past the 11th full hour.
    private const EXTENDED_REST_TRIGGER_MINUTES = 11 * Units::MINUTES_PER_HOUR;

    /**
     * @return list<ComplianceWarning>
     */
    public function check(WeekResult $week, ?DayResult $previousDay = null): array
    {
        $warnings = [];
        foreach ($week->days as $day) {
            if ($day->grossMinutes > self::DAILY_MAX_MINUTES) {
                $warnings[] = new ComplianceWarning(ComplianceIssue::DAILY_MAX, $day->begin, $day->grossMinutes, self::DAILY_MAX_MINUTES);
            }
        }

        if ($week->workMinutes > self::WEEKLY_MAX_MINUTES && $week->days !== []) {
            $warnings[] = new ComplianceWarning(ComplianceIssue::WEEKLY_MAX, $week->days[0]->begin, $week->workMinutes, self::WEEKLY_MAX_MINUTES);
        }

        return [...$warnings, ...$this->restTimeWarnings($week->days, $previousDay)];
    }

    /**
     * @param list<DayResult> $days sorted by begin (WeekCalculator's contract)
     * @return list<ComplianceWarning>
     */
    private function restTimeWarnings(array $days, ?DayResult $previousDay): array
    {
        $all = $previousDay !== null ? [$previousDay, ...$days] : $days;

        $warnings = [];
        for ($i = 1; $i < count($all); ++$i) {
            $previous = $all[$i - 1];
            $current = $all[$i];
            $rest = intdiv($current->begin->getTimestamp() - $previous->end->getTimestamp(), 60);
            $required = $previous->grossMinutes > self::EXTENDED_REST_TRIGGER_MINUTES
                ? self::REST_EXTENDED_MINUTES
                : self::REST_MINUTES;

            if ($rest < $required) {
                $warnings[] = new ComplianceWarning(ComplianceIssue::REST_TIME, $current->begin, max(0, $rest), $required);
            }
        }

        return $warnings;
    }
}
