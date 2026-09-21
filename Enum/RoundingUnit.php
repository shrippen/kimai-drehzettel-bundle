<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

enum RoundingUnit: int
{
    case MINUTE = 1;
    case QUARTER = 15;
    case HALF_HOUR = 30;
    case HOUR = 60;
}
