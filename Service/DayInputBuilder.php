<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;

/**
 * Kimai timesheet entries + film day data -> calculator input.
 *
 * One shooting day is one continuous span: earliest begin to latest end
 * of all entries that start on that date.
 */
class DayInputBuilder
{
    private const DATE_FORMAT = 'Y-m-d';
    private const ISO_SATURDAY = '6';
    private const ISO_SUNDAY = '7';

    public function __construct(
        private readonly TimesheetRangeRepository $timesheets,
        private readonly FilmDayRepository $filmDays,
    ) {
    }

    /**
     * @return list<DayInput> for entries beginning in [from, to), inside the engagement's validity
     */
    public function build(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $spans = $this->spans($engagement, $from, $to);
        $film = $this->filmDaysByDate($engagement, $from, $to);

        $inputs = [];
        foreach ($spans as $key => [$begin, $end]) {
            $extra = $film[$key] ?? null;
            $inputs[] = new DayInput(
                begin: $begin,
                end: $end,
                category: $extra?->getCategory() ?? $this->weekdayCategory($begin),
                type: $extra?->getDayType() ?? DayType::WORKDAY,
                catering: $extra?->getCatering() ?? Catering::NO,
                breakMinutes: $extra?->getBreakMinutes(),
                productionDay: $extra?->getProductionDay(),
                note: $extra?->getNote(),
            );
        }

        return $inputs;
    }

    /**
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable}>
     */
    private function spans(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = $this->timesheets->findClosed($engagement->getUser(), $engagement->getProject(), $from, $to);

        $spans = [];
        foreach ($entries as $entry) {
            $begin = \DateTimeImmutable::createFromInterface($entry->getBegin());
            $end = \DateTimeImmutable::createFromInterface($entry->getEnd());
            $key = $begin->format(self::DATE_FORMAT);
            if (!$this->isValid($engagement, $key)) {
                continue;
            }

            $known = $spans[$key] ?? [$begin, $end];
            $spans[$key] = [min($known[0], $begin), max($known[1], $end)];
        }
        ksort($spans);

        return $spans;
    }

    private function isValid(Engagement $engagement, string $dateKey): bool
    {
        $from = $engagement->getValidFrom()->format(self::DATE_FORMAT);
        $to = $engagement->getValidTo()?->format(self::DATE_FORMAT);

        return $dateKey >= $from && ($to === null || $dateKey <= $to);
    }

    /**
     * @return array<string, FilmDay>
     */
    private function filmDaysByDate(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $byDate = [];
        foreach ($this->filmDays->findRange($engagement, $from, $to) as $day) {
            $byDate[$day->getDate()->format(self::DATE_FORMAT)] = $day;
        }

        return $byDate;
    }

    // Holidays are not known here yet. The Holiday plugin integration comes later.
    private function weekdayCategory(\DateTimeImmutable $date): DayCategory
    {
        return match ($date->format('N')) {
            self::ISO_SATURDAY => DayCategory::SATURDAY,
            self::ISO_SUNDAY => DayCategory::SUNDAY,
            default => DayCategory::WORKDAY,
        };
    }
}
