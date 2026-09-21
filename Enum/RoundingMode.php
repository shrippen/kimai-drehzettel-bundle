<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

enum RoundingMode: string
{
    case UP = 'up';
    case DOWN = 'down';
    case NEAREST = 'nearest';
}
