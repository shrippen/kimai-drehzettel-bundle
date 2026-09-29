<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Timesheet;

// Saves a changed Kimai entry. The app uses Kimai's TimesheetService (its events run); dev scripts flush.
interface TimesheetWriter
{
    public function update(Timesheet $timesheet): void;
}
