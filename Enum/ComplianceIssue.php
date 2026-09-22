<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

// TV FFS 5.2.5 (max hours) and 5.9.1 (rest time).
enum ComplianceIssue: string
{
    case DAILY_MAX = 'daily_max';
    case WEEKLY_MAX = 'weekly_max';
    case REST_TIME = 'rest_time';
}
