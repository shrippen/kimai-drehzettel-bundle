<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// Every column and section of the timesheet PDF that can be switched off.
enum PdfOption: string
{
    case BREAK = 'break';
    case TIERS = 'tiers';
    case NIGHT = 'night';
    case UNDER = 'under';
    case CATERING = 'catering';
    case DAY_TYPE = 'day_type';
    case PAY = 'pay';
    case ALL_WEEKDAYS = 'all_weekdays';
    case WEEKLY_OVERTIME = 'weekly_overtime';
    case NOTES = 'notes';
    case SIGNATURE_LINES = 'signature_lines';
    case SIGNATURE_IMAGE = 'signature_image';
    case ROUNDING_NOTE = 'rounding_note';
}
