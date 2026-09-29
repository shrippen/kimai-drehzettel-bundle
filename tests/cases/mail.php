<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\MailSchedule;
use KimaiPlugin\DrehzettelBundle\Domain\MailTemplate;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;

$berlin = new DateTimeZone('Europe/Berlin');
$now = static fn (string $time): DateTimeImmutable => new DateTimeImmutable($time, $berlin);
$slot = static fn (MailSchedule $s, string $time): ?string => $s->lastSlot($now($time))?->format('Y-m-d H:i');

// Weekly, Monday 07:00: the send time of this week once passed, else last week's.
$monday = new MailSchedule(MailRhythm::WEEKLY, 1, 7);
check('mail weekly before slot', '2026-09-21 07:00', $slot($monday, '2026-09-28 06:59'));
check('mail weekly at slot', '2026-09-28 07:00', $slot($monday, '2026-09-28 07:00'));
check('mail weekly later in week', '2026-09-28 07:00', $slot($monday, '2026-10-04 23:00'));
check('mail weekly next', '2026-10-05 07:00', $monday->nextSlot($now('2026-09-28 08:00'))?->format('Y-m-d H:i'));

// The mailed week is the week of the day before: Monday morning -> last week, Friday -> this week.
$period = $monday->period($now('2026-09-28 07:00'));
check('mail weekly period monday', ['2026-09-21', '2026-09-27'], [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);
$friday = new MailSchedule(MailRhythm::WEEKLY, 5, 18);
$period = $friday->period($friday->lastSlot($now('2026-10-02 19:00')));
check('mail weekly period friday', ['2026-09-28', '2026-10-04'], [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

// Sunday 20:00 across the year: ISO week 53 of 2026.
$sunday = new MailSchedule(MailRhythm::WEEKLY, 7, 20);
check('mail weekly sunday new year', '2027-01-03 20:00', $slot($sunday, '2027-01-04 10:00'));
$period = $sunday->period($sunday->lastSlot($now('2027-01-04 10:00')));
check('mail weekly period week 53', ['2026-12-28', '2027-01-03'], [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);

// Monthly on the 1st: the previous month.
$monthly = new MailSchedule(MailRhythm::MONTHLY, 1, 7);
check('mail monthly before slot', '2026-09-01 07:00', $slot($monthly, '2026-10-01 06:00'));
check('mail monthly at slot', '2026-10-01 07:00', $slot($monthly, '2026-10-01 07:30'));
check('mail monthly next', '2026-11-01 07:00', $monthly->nextSlot($now('2026-10-15 12:00'))?->format('Y-m-d H:i'));
$period = $monthly->period($now('2026-10-01 07:00'));
check('mail monthly period', ['2026-09-01', '2026-09-30'], [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);
check('mail monthly january', '2026-12-01 07:00', $slot($monthly, '2027-01-01 06:00'));

// Off: never due.
check('mail off', [null, null], [$slot(new MailSchedule(MailRhythm::OFF), '2026-09-28 07:00'), (new MailSchedule(MailRhythm::OFF))->nextSlot($now('2026-09-28 07:00'))]);

$threw = false;
try {
    new MailSchedule(MailRhythm::WEEKLY, 8, 7);
} catch (InvalidArgumentException) {
    $threw = true;
}
check('mail schedule rejects weekday 8', true, $threw);

// Placeholders: known ones replaced, unknown ones kept as typed.
$template = new MailTemplate('Stundenzettel {period} – {project}', "Hallo,\n\n{name} {unknown}");
$filled = $template->render(['period' => 'KW 39/2026', 'project' => 'Graufeld', 'name' => 'Erika']);
check('mail template subject', 'Stundenzettel KW 39/2026 – Graufeld', $filled->subject);
check('mail template body', "Hallo,\n\nErika {unknown}", $filled->body);
check('mail template missing value', 'x  y', (new MailTemplate('x {role} y', ''))->render([])->subject);
