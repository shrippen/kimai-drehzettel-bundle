<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\DayResult;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\Streak;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/**
 * Results for an engagement: one week, or any period split by ISO week.
 * Weekly overtime needs whole calendar weeks, so periods are never
 * calculated as one block.
 */
class FilmWeekService
{
    private const WEEK_FORMAT = 'o-W';
    private const BOUNDARY_LOOKBACK_DAYS = 7;

    public function __construct(
        private readonly DayInputBuilder $inputs,
        private readonly WeekCalculator $weeks,
        private readonly DayCalculator $days,
        private readonly EngagementService $engagements,
        private readonly ConsecutiveDayCounter $streaks,
    ) {
    }

    /**
     * @param array<string, FilmDayDraft> $drafts unsaved film day data by date, for live previews
     */
    public function week(Engagement $engagement, int $isoYear, int $isoWeek, array $drafts = []): WeekResult
    {
        $monday = $this->monday($engagement, $isoYear, $isoWeek);

        return $this->calc($engagement, $this->inputs->build($engagement, $monday, $monday->modify('+7 days'), $drafts));
    }

    /**
     * Calculates every ISO week that touches [from, to). Weekly overtime needs
     * whole weeks, so a range that starts mid-week still reads the full week.
     *
     * @return list<WeekResult> one per ISO week that has entries, in date order
     */
    public function period(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $start = $from->modify('monday this week');
        $end = $to->modify('-1 day')->modify('monday this week')->modify('+7 days');

        $byWeek = [];
        foreach ($this->inputs->build($engagement, $start, $end) as $input) {
            $byWeek[$input->begin->format(self::WEEK_FORMAT)][] = $input;
        }
        ksort($byWeek);

        // Only the first week looks back; later weeks carry N from the week before.
        $results = [];
        $last = null;
        foreach ($byWeek as $days) {
            $result = $this->calc($engagement, $days, $last === null ? null : Streak::carry($last, $days[0]));
            $results[] = $result;
            $last = $result->days[count($result->days) - 1];
        }

        return $results;
    }

    /**
     * The last shooting day strictly before $date, for rest-time compliance checks that
     * reach across a week boundary (last day of one week to the first day of the next).
     * Calculated on its own, without a weekly pool, since only begin/end/gross are needed.
     */
    public function lastDayBefore(Engagement $engagement, \DateTimeImmutable $date): ?DayResult
    {
        $from = $date->modify(sprintf('-%d days', self::BOUNDARY_LOOKBACK_DAYS));
        $inputs = $this->inputs->build($engagement, $from, $date);
        if ($inputs === []) {
            return null;
        }

        return $this->days->calc($inputs[count($inputs) - 1], $this->engagements->ruleset($engagement), null);
    }

    /**
     * @param list<DayInput> $days sorted by begin
     * @param ?int $streakBefore N of the day before the first day; null looks it up
     */
    private function calc(Engagement $engagement, array $days, ?int $streakBefore = null): WeekResult
    {
        $rules = $this->engagements->ruleset($engagement);

        // Only consecutive counting reaches into earlier weeks.
        if ($streakBefore === null && $days !== [] && $rules->streakMode === StreakMode::CONSECUTIVE) {
            $streakBefore = $this->streaks->before($engagement, $days[0]->begin);
        }

        return $this->weeks->calc(
            $days,
            $rules,
            $this->engagements->terms($engagement),
            $streakBefore ?? 0,
        );
    }

    // Monday 00:00 in the user's timezone, so entries are matched by local date.
    private function monday(Engagement $engagement, int $isoYear, int $isoWeek): \DateTimeImmutable
    {
        $zone = $engagement->getUser()->getTimezone();

        return (new \DateTimeImmutable('today', new \DateTimeZone($zone)))
            ->setISODate($isoYear, $isoWeek)
            ->setTime(0, 0);
    }
}
