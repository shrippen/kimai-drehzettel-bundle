<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;

// Plain flush, no Kimai events: for the dev and demo seed scripts, which build services by hand.
class FlushTimesheetWriter implements TimesheetWriter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function update(Timesheet $timesheet): void
    {
        $this->em->flush();
    }
}
