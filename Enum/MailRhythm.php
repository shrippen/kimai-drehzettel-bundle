<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// Automatic timesheet mail: off, every week or every month.
enum MailRhythm: string
{
    case OFF = 'off';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
