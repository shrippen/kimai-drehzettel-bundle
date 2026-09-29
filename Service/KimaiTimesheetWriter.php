<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Timesheet;
use App\Timesheet\TimesheetService;

class KimaiTimesheetWriter implements TimesheetWriter
{
    public function __construct(private readonly TimesheetService $service)
    {
    }

    public function update(Timesheet $timesheet): void
    {
        $this->service->updateTimesheet($timesheet);
    }
}
