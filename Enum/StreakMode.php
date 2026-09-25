<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

/**
 * How the 6th/7th day is counted (Domain\Streak):
 *
 *   CALENDAR_WEEK  n-th day with entry in the ISO week (TV FFS TZ 5.4.3.1/5.4.3.4), max 7
 *   CONSECUTIVE    n-th day in a row across weeks; a day without entry resets; day 8+ = day 7
 *
 * Mo Di (Mi frei) Do Fr Sa So: calendar week makes Sunday day 6, consecutive day 4.
 * Mi..Di durchgehend: calendar week makes Mo/Di day 1/2, consecutive day 6/7.
 */
enum StreakMode: string
{
    case CALENDAR_WEEK = 'calendarWeek';
    case CONSECUTIVE = 'consecutive';

    // Mode of presets and of stored rulesets without the key. Product-owner decision pending.
    public const DEFAULT = self::CALENDAR_WEEK;

    private const DAYS_PER_WEEK = 7;
    private const MAX_CONSECUTIVE = 999;

    // Highest productionDay override.
    public function maxDay(): int
    {
        return $this === self::CONSECUTIVE ? self::MAX_CONSECUTIVE : self::DAYS_PER_WEEK;
    }
}
