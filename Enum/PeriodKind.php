<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

enum PeriodKind: string
{
    case WEEK = 'week';
    case MONTH = 'month';
    case RANGE = 'range';
}
