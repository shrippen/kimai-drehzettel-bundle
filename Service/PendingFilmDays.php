<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Timesheet;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;

/**
 * Film day fields from Kimai's timesheet form, held until Kimai has saved the entry:
 *
 *   form POST_SUBMIT ──put──► PendingFilmDays ──take──► Timesheet{Create,Update}PostEvent
 *
 * The form must not flush: validation has not run yet, and a flush would write the
 * half-submitted timesheet past Kimai's own checks. A WeakMap drops the fields of an
 * entry that was never saved (invalid form).
 */
class PendingFilmDays
{
    /** @var \WeakMap<Timesheet, FilmDayPatch> */
    private \WeakMap $patches;

    public function __construct()
    {
        $this->patches = new \WeakMap();
    }

    public function put(Timesheet $timesheet, FilmDayPatch $patch): void
    {
        $this->patches[$timesheet] = $patch;
    }

    public function take(Timesheet $timesheet): ?FilmDayPatch
    {
        $patch = $this->patches[$timesheet] ?? null;
        unset($this->patches[$timesheet]);

        return $patch;
    }
}
