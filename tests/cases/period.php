<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\Period;

// Week form keys -> dates. Sunday must not be dropped by a time-of-day left on the parsed date.
$zone = new DateTimeZone('America/New_York');
$week = Period::week(2025, 21, $zone);
check('period day monday', '2025-05-19 00:00 America/New_York', $week->day('2025-05-19')?->format('Y-m-d H:i e'));
check('period day sunday', '2025-05-25 00:00 America/New_York', $week->day('2025-05-25')?->format('Y-m-d H:i e'));
check('period day outside', [null, null], [$week->day('2025-05-18'), $week->day('2025-05-26')]);
check('period day junk', [null, null, null], [$week->day('2025-02-30'), $week->day('bogus'), $week->day('2025-05-20x')]);
