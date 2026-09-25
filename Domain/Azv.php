<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * AZV credit (Arbeitszeitverkürzung), TV FFS TZ 6.1-6.7.
 *
 * TZ 6.1: 2.5 h after 5 full shooting days in a row, 0.5 h for each further one.
 * TZ 6.3/6.4: counted per block of 20 shooting days, 10 h = one AZV day.
 *
 *   shooting day   1-4  5    6-20        | 21-24  25   26-40       | ...
 *   credit         0    2.5  +0.5 each   | 0      2.5  +0.5 each   |
 *   total after    0    2.5  10 (day 20) | 10     12.5 20 (day 40) |
 *
 * PA FAQ: 24 days -> 10 h, 26 days -> 10 h + 3 h.
 *
 * "Zusammenhängend" is read from footnote 2 of TZ 6.1: "an mindestens 5
 * aufeinanderfolgenden Drehtagen", i.e. consecutive shooting days of the
 * engagement. Days off and travel days are no shooting days: they neither
 * count nor break the row. The row ends with the engagement.
 */
final class Azv
{
    // TZ 6.7: in force for shoots beginning on or after this date.
    public const EFFECTIVE_FROM = '2025-05-01';

    // TZ 6.2: one AZV day is a shooting day of 10 h.
    public const DAY_MINUTES = 10 * Units::MINUTES_PER_HOUR;

    public const BLOCK_DAYS = 20;

    private const THRESHOLD_DAYS = 5;
    private const THRESHOLD_MINUTES = 150;
    private const PER_DAY_MINUTES = 30;

    // Credit after n shooting days: 5 -> 150, 20 -> 600, 24 -> 600, 26 -> 780.
    public static function minutes(int $shootingDays): int
    {
        $blocks = intdiv($shootingDays, self::BLOCK_DAYS);
        $rest = $shootingDays % self::BLOCK_DAYS;
        $partial = $rest < self::THRESHOLD_DAYS ? 0 : self::THRESHOLD_MINUTES + ($rest - self::THRESHOLD_DAYS) * self::PER_DAY_MINUTES;

        return $blocks * self::DAY_MINUTES + $partial;
    }

    /**
     * Without an explicit choice: TV FFS 2024 engagements starting from 2025-05-01 (TZ 6.7).
     * Crew behind the camera and no high-frequency series (TZ 6.1, 6.5) are assumed.
     */
    public static function byDefault(string $rulesetName, \DateTimeImmutable $validFrom): bool
    {
        return $rulesetName === Rulesets::tvFfs2024()->name && $validFrom->format('Y-m-d') >= self::EFFECTIVE_FROM;
    }

    public static function eligible(Engagement $engagement): bool
    {
        return $engagement->getAzv() ?? self::byDefault($engagement->getRulesetName(), $engagement->getValidFrom());
    }

    // First day that counts: the engagement start, not before TZ 6 came into force.
    public static function countsFrom(Engagement $engagement): \DateTimeImmutable
    {
        $zone = $engagement->getUser()->getDateTimezone();
        $start = new \DateTimeImmutable($engagement->getValidFrom()->format('Y-m-d'), $zone);

        return max($start, new \DateTimeImmutable(self::EFFECTIVE_FROM, $zone));
    }
}
