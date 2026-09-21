<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// TV FFS 5.8.2: a break counts as work time beyond 45 min.
enum BreakRule: string
{
    case DEDUCT_ALL = 'deduct_all';
    case EXCESS_COUNTS_AS_WORK = 'excess_counts_as_work';
}
