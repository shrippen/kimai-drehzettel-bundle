<?php

namespace KimaiPlugin\DrehzettelBundle\Enum;

/**
 * Whether travel days count for day N (6th/7th day) and weekly overtime.
 *
 * EXCLUDED: tariff reading. Travel time is paid "wie normale Arbeitszeit ohne
 * Zuschläge" (TV FFS TZ 12.1), so a travel day is no working day.
 * COUNTED: a travel day counts like a working day for N and the weekly pool;
 * its own time still gets no daily, night or category surcharge.
 */
enum TravelDays: string
{
    case EXCLUDED = 'excluded';
    case COUNTED = 'counted';
}
