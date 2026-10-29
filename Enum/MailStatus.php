<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// Outcome of a timesheet mail. The values are the kit's status keys (kit.status_badge).
enum MailStatus: string
{
    case SENT = 'sent';
    case FAILED = 'failed';
    case SKIPPED = 'skipped'; // automatic mail of a period without entries
}
