<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

enum DayCategory: string
{
    case WORKDAY = 'workday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';
    case HOLIDAY = 'holiday';
}
