<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\WeekResult;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * Results for an engagement: one week, or any period split by ISO week.
 * Weekly overtime needs whole calendar weeks, so periods are never
 * calculated as one block.
 */
class FilmWeekService
{
    private const WEEK_FORMAT = 'o-W';

    public function __construct(
        private readonly DayInputBuilder $inputs,
        private readonly WeekCalculator $weeks,
        private readonly EngagementService $engagements,
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

        return array_map(fn (array $days): WeekResult => $this->calc($engagement, $days), array_values($byWeek));
    }

    /**
     * @param list<DayInput> $days
     */
    private function calc(Engagement $engagement, array $days): WeekResult
    {
        return $this->weeks->calc(
            $days,
            $this->engagements->ruleset($engagement),
            $this->engagements->terms($engagement),
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
