<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\ComplianceIssue;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/**
 * GET /v1/days/{date}/summary: one day out of its calculated week.
 *
 *   {"hasEntry": true, "workMinutes": 645, "overtime": [{"percent": 25, "minutes": 45}, ...],
 *    "nightMinutes": 0, "payCents": 36134, "extraPayCents": 2500, "warnings": [...]}
 *
 * payCents is the day's pay including extra pay, excluding weekly overtime
 * (that belongs to the week, see weeklyOvertimeMinutes). Null without a gage.
 *
 * consecutiveDay repeats dayNumber (the day behind the 6th/7th-day surcharge),
 * counted per streakMode: n-th day of the ISO week, or n-th day in a row.
 */
final class DaySummary
{
    private const DATE_FORMAT = 'Y-m-d';
    private const DEFAULT_CURRENCY = 'EUR';

    /**
     * @param list<ComplianceWarning> $warnings of the whole week
     * @param StreakMode $mode of the engagement's ruleset
     * @return array<string, mixed>
     */
    public static function of(WeekResult $week, string $dateKey, array $warnings, Engagement $engagement, StreakMode $mode): array
    {
        $hasPay = $engagement->getGageCents() > 0;
        $day = null;
        foreach ($week->days as $candidate) {
            if ($candidate->begin->format(self::DATE_FORMAT) === $dateKey) {
                $day = $candidate;
            }
        }

        return [
            'date' => $dateKey,
            'engagementId' => $engagement->getId(),
            'hasEntry' => $day !== null,
            'begin' => $day?->begin->format(\DateTimeInterface::ATOM),
            'end' => $day?->end->format(\DateTimeInterface::ATOM),
            'workMinutes' => $day?->workMinutes ?? 0,
            'breakMinutes' => $day?->breakMinutes ?? 0,
            'overtime' => array_map(self::share(...), $day?->dailyShares ?? []),
            'nightMinutes' => $day?->nightMinutes ?? 0,
            'underMinutes' => $day?->underMinutes ?? 0,
            'category' => $day?->category->value,
            'categoryPercent' => $day?->categorySurcharge === null ? null : self::percent($day->categorySurcharge->basisPoints),
            'dayNumber' => $day?->dayNumber,
            'shootingDayNumber' => $day?->shootingDayNumber,
            'weeklyOvertimeMinutes' => $week->weeklyPoolMinutes,
            'warnings' => $day === null ? [] : self::warnings($warnings, $dateKey),
            'payCents' => $hasPay ? $day?->amountCents : null,
            'extraPayCents' => $day?->extraPayCents ?? 0,
            'currency' => $engagement->getProject()?->getCustomer()?->getCurrency() ?? self::DEFAULT_CURRENCY,
            'consecutiveDay' => $day?->dayNumber,
            'consecutiveDayOverridden' => $day?->productionDay !== null,
            'streakMode' => $mode->value,
        ];
    }

    /**
     * The day's own warnings plus the week limit, which no single day owns.
     *
     * @param list<ComplianceWarning> $warnings
     * @return list<array{issue: string, minutes: int, limitMinutes: int}>
     */
    private static function warnings(array $warnings, string $dateKey): array
    {
        $own = array_filter($warnings, static fn (ComplianceWarning $w): bool => $w->issue === ComplianceIssue::WEEKLY_MAX || $w->date->format(self::DATE_FORMAT) === $dateKey);

        return array_values(array_map(static fn (ComplianceWarning $w): array => [
            'issue' => $w->issue->value,
            'minutes' => $w->minutes,
            'limitMinutes' => $w->limitMinutes,
        ], $own));
    }

    /**
     * @return array{percent: float, minutes: int}
     */
    private static function share(Share $share): array
    {
        return ['percent' => self::percent($share->basisPoints), 'minutes' => $share->minutes];
    }

    // 2500 basis points -> 25.0
    private static function percent(int $basisPoints): float
    {
        return $basisPoints / (Units::BASIS_POINTS / 100);
    }
}
