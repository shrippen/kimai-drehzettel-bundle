<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// TV FFS 5.2.5 (max hours), 5.9.1 (rest time); tariff readings to check: 5.6.3 (staggered shoot), 5.2.4 (night past 04:00).
enum ComplianceIssue: string
{
    case DAILY_MAX = 'daily_max';
    case WEEKLY_MAX = 'weekly_max';
    case REST_TIME = 'rest_time';
    case STAGGERED_SHOOT = 'staggered_shoot';
    case NIGHT_CUTOFF = 'night_cutoff';
}
