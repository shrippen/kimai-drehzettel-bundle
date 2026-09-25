<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Service\HolidayLookupInterface;
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
        private readonly HolidayLookupInterface $holidays,
    ) {
    }

    /**
     * @param array<string, FilmDayDraft> $drafts unsaved film day data by date (Y-m-d), wins over stored data
     * @return list<DayInput> for entries beginning in [from, to), inside the engagement's validity
     */
    public function build(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to, array $drafts = []): array
    {
        $spans = $this->spans($engagement, $from, $to);
        $film = $this->filmDaysByDate($engagement, $from, $to);

        $inputs = [];
        foreach ($spans as $key => [$begin, $end]) {
            $extra = isset($drafts[$key]) ? $this->fromDraft($drafts[$key]) : ($film[$key] ?? null);
            $inputs[] = new DayInput(
                begin: $begin,
                end: $end,
                category: $extra?->getCategory() ?? $this->categoryFor($engagement, $begin),
                type: $extra?->getDayType() ?? DayType::WORKDAY,
                catering: $extra?->getCatering() ?? Catering::NO,
                breakMinutes: $extra?->getBreakMinutes(),
                productionDay: $extra?->getProductionDay(),
                note: $extra?->getNote(),
                extraPayCents: $extra?->getExtraPayCents() ?? 0,
                shootingDayNumber: $extra?->getShootingDayNumber(),
            );
        }

        return $inputs;
    }

    /**
     * Days in [from, to) with an entry that is no travel day, for the AZV count.
     * Lighter than build(): no holiday lookup, no calculator input.
     *
     * @param array<string, FilmDayDraft> $drafts unsaved film day data by date, wins over stored data
     */
    public function shootingDays(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to, array $drafts = []): int
    {
        $film = $this->filmDaysByDate($engagement, $from, $to);

        $count = 0;
        foreach (array_keys($this->spans($engagement, $from, $to)) as $key) {
            $type = isset($drafts[$key]) ? $drafts[$key]->type : ($film[$key] ?? null)?->getDayType();
            if (($type ?? DayType::WORKDAY) === DayType::WORKDAY) {
                ++$count;
            }
        }

        return $count;
    }

    // A draft is applied through a throw-away FilmDay, so both sources read alike.
    private function fromDraft(FilmDayDraft $draft): FilmDay
    {
        $day = new FilmDay();
        $day->setBreakMinutes($draft->breakMinutes);
        $day->setCatering($draft->catering);
        $day->setCategory($draft->category);
        $day->setDayType($draft->type);
        $day->setProductionDay($draft->productionDay);
        $day->setNote($draft->note);
        $day->setExtraPayCents($draft->extraPayCents);
        $day->setShootingDayNumber($draft->shootingDayNumber);

        return $day;
    }

    /**
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable}>
     */
    private function spans(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Days are keyed by the entry's own local date, as Kimai shows it. That zone can differ
        // from the user's zone of [from, to) (Berlin 00:30 Monday = UTC 22:30 Sunday), so query
        // a day wider and filter by key: otherwise such an entry lands in the wrong week.
        $entries = $this->timesheets->findClosed($engagement->getUser(), $engagement->getProject(), $from->modify('-1 day'), $to->modify('+1 day'));
        $fromKey = $from->format(self::DATE_FORMAT);
        $toKey = $to->format(self::DATE_FORMAT);

        $spans = [];
        foreach ($entries as $entry) {
            $begin = \DateTimeImmutable::createFromInterface($entry->getBegin());
            $end = \DateTimeImmutable::createFromInterface($entry->getEnd());
            $key = $begin->format(self::DATE_FORMAT);
            if ($key < $fromKey || $key >= $toKey || !$this->isValid($engagement, $key)) {
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

    // A film day override always wins (checked by the caller); this is only the fallback.
    public function categoryFor(Engagement $engagement, \DateTimeImmutable $date): DayCategory
    {
        if ($this->holidays->isHoliday($engagement->getUser(), $date)) {
            return DayCategory::HOLIDAY;
        }

        return $this->weekdayCategory($date);
    }

    private function weekdayCategory(\DateTimeImmutable $date): DayCategory
    {
        return match ($date->format('N')) {
            self::ISO_SATURDAY => DayCategory::SATURDAY,
            self::ISO_SUNDAY => DayCategory::SUNDAY,
            default => DayCategory::WORKDAY,
        };
    }
}
