<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// HOURLY: percent of the hourly rate per worked hour.
// DAY_RATE: percent of the day rate, once per day (TV FFS 5.7.3).
enum SurchargeBasis: string
{
    case HOURLY = 'hourly';
    case DAY_RATE = 'day_rate';
}
