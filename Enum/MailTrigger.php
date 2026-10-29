<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// Who sent a timesheet mail: the schedule (MailSchedule) or the user in the week view.
enum MailTrigger: string
{
    case AUTOMATIC = 'automatic';
    case MANUAL = 'manual';
}
