<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Timesheet;
use KimaiPlugin\DrehzettelBundle\Domain\DayNote;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;

/**
 * Note of a film day = description of the day's Kimai entries (Domain\DayNote).
 * Entries of an activity the engagement excludes (a private commute) do not count.
 */
class DayNotes
{
    private const DATE_KEY = 'Y-m-d';

    public function __construct(
        private readonly TimesheetRangeRepository $timesheets,
        private readonly TimesheetWriter $writer,
    ) {
    }

    public function read(Engagement $engagement, \DateTimeImmutable $date): ?string
    {
        return $this->byDate($engagement, $date, $date->modify('+1 day'))[$date->format(self::DATE_KEY)] ?? null;
    }

    /**
     * Notes by date (Y-m-d) of the entries beginning in [from, to).
     *
     * @return array<string, string>
     */
    public function byDate(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $descriptions = [];
        foreach ($this->entries($engagement, $from, $to) as $key => $entries) {
            $descriptions[$key] = DayNote::join(array_map(static fn (Timesheet $t): ?string => $t->getDescription(), $entries));
        }

        return array_filter($descriptions, static fn (?string $note): bool => $note !== null);
    }

    /**
     * Stores the note in the descriptions of the day's entries. False when the
     * date has no entry to hold it.
     */
    public function write(Engagement $engagement, \DateTimeImmutable $date, ?string $note): bool
    {
        $entries = $this->entries($engagement, $date, $date->modify('+1 day'))[$date->format(self::DATE_KEY)] ?? [];
        if ($entries === []) {
            return DayNote::clean($note) === null;
        }

        $assigned = DayNote::assign(array_map(static fn (Timesheet $t): ?string => $t->getDescription(), $entries), $note);
        if ($assigned === null) {
            return true;
        }

        foreach ($entries as $i => $entry) {
            if ($entry->getDescription() === $assigned[$i]) {
                continue;
            }
            $entry->setDescription($assigned[$i]);
            $this->writer->update($entry);
        }

        return true;
    }

    /**
     * Film entries by their own local date, as Kimai shows it. A day wider, then
     * matched by date: see DayInputBuilder::spans().
     *
     * @return array<string, list<Timesheet>>
     */
    private function entries(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromKey = $from->format(self::DATE_KEY);
        $toKey = $to->format(self::DATE_KEY);

        $byDate = [];
        foreach ($this->timesheets->findStarting($engagement->getUser(), $engagement->getProject(), $from->modify('-1 day'), $to->modify('+1 day')) as $entry) {
            if (!$engagement->appliesToActivity($entry->getActivity())) {
                continue;
            }
            $key = EngagementService::dateOf($entry)->format(self::DATE_KEY);
            if ($key >= $fromKey && $key < $toKey) {
                $byDate[$key][] = $entry;
            }
        }

        return $byDate;
    }
}
